<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Jeder Text aus der .pot ist in jeder Sprache übersetzt, und die .mo entspricht der .po. */
final class UebersetzungenTest extends TestCase
{
    /** Slug auf wordpress.org, zugleich Text Domain, Plugin-Ordner und Präfix der Übersetzungsdateien */
    private const SLUG = 'strainovic-it-rappenrundung';
    /** Übersetzungen zum Einspielen auf translate.wordpress.org, nicht Teil des Plugin-Pakets */
    private const ORDNER = __DIR__ . '/../translations';

    /** @return iterable<string, array{string}> */
    public static function sprachen(): iterable
    {
        foreach (['de_DE', 'de_CH', 'fr_FR', 'it_IT'] as $s) {
            yield $s => [$s];
        }
    }

    public function testPotEnthaeltDieTexteDesPlugins(): void
    {
        $pot = self::po(self::ORDNER . '/' . self::SLUG . '.pot');

        self::assertArrayHasKey('Rounding to 5 cents', $pot);
        self::assertArrayHasKey('Rounding line', $pot);
        foreach (self::quelltexte() as $text) {
            self::assertArrayHasKey($text, $pot, "«{$text}» fehlt in der .pot, wp i18n make-pot neu laufen lassen");
        }
    }

    #[DataProvider('sprachen')]
    public function testAllesUebersetztMitGleichenPlatzhaltern(string $sprache): void
    {
        $pot = self::po(self::ORDNER . '/' . self::SLUG . '.pot');
        $po = self::po(self::ORDNER . '/' . self::SLUG . "-$sprache.po");

        foreach (array_keys($pot) as $msgid) {
            self::assertArrayHasKey($msgid, $po, "$sprache: «{$msgid}» fehlt");
            self::assertNotSame('', $po[$msgid], "$sprache: «{$msgid}» ist leer");
            self::assertSame(self::platzhalter($msgid), self::platzhalter($po[$msgid]), "$sprache: Platzhalter in «{$msgid}»");
        }
    }

    #[DataProvider('sprachen')]
    public function testMoEntsprichtDerPo(string $sprache): void
    {
        $po = array_filter(self::po(self::ORDNER . '/' . self::SLUG . "-$sprache.po"), static fn (string $s): bool => $s !== '');

        self::assertEquals($po, self::mo(self::ORDNER . '/' . self::SLUG . "-$sprache.mo"), "$sprache: .mo veraltet, msgfmt neu laufen lassen");
    }

    public function testSchweizerDeutschOhneEszett(): void
    {
        foreach (self::po(self::ORDNER . '/' . self::SLUG . '-de_CH.po') as $uebersetzung) {
            self::assertStringNotContainsString('ß', $uebersetzung);
        }
    }

    /** Texte in gettext-Aufrufen des Quellcodes, damit eine veraltete .pot auffällt. @return list<string> */
    private static function quelltexte(): array
    {
        $texte = [];
        foreach (glob(__DIR__ . '/../plugin/' . self::SLUG . '/src/*.php') ?: [] as $datei) {
            $code = (string) file_get_contents($datei);
            // Jeder gettext-Aufruf muss die Text Domain des Slugs tragen (Plugin Check verlangt Slug = Text Domain)
            preg_match_all("/\\b(?:__|esc_html__|esc_attr__|_e|esc_html_e)\\(\\s*'(?:[^'\\\\]|\\\\.)*'\\s*,\\s*'([^']*)'/", $code, $domains);
            foreach ($domains[1] as $domain) {
                self::assertSame(self::SLUG, $domain, basename($datei) . ': falsche Text Domain');
            }
            preg_match_all("/\\b(?:__|esc_html__|esc_attr__|_e|esc_html_e)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'" . self::SLUG . "'/", $code, $m);
            foreach ($m[1] as $t) {
                $texte[] = stripcslashes($t);
            }
        }

        return $texte;
    }

    /** @return list<string> */
    private static function platzhalter(string $text): array
    {
        preg_match_all('/%(?:\d+\$)?[sdf]/', $text, $m);
        $p = $m[0];
        sort($p);

        return $p;
    }

    /** Einfacher .po-Leser (msgctxt, mehrzeilige Strings; ohne Plural). @return array<string, string> msgid => msgstr */
    private static function po(string $datei): array
    {
        self::assertFileExists($datei);
        $eintraege = [];
        $aktuell = [];
        $feld = null;
        $abschliessen = static function () use (&$aktuell, &$eintraege): void {
            if (isset($aktuell['msgid']) && $aktuell['msgid'] !== '') {
                $schluessel = isset($aktuell['msgctxt']) ? $aktuell['msgctxt'] . "\x04" . $aktuell['msgid'] : $aktuell['msgid'];
                $eintraege[$schluessel] = $aktuell['msgstr'] ?? '';
            }
            $aktuell = [];
        };
        foreach (file($datei, FILE_IGNORE_NEW_LINES) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile === '' || str_starts_with($zeile, '#')) {
                if ($zeile === '') {
                    $abschliessen();
                    $feld = null;
                }
                continue;
            }
            if (preg_match('/^(msgctxt|msgid|msgstr)\s+"(.*)"$/', $zeile, $m)) {
                if ($m[1] === 'msgid' && isset($aktuell['msgid'])) {
                    $abschliessen();
                }
                $feld = $m[1];
                $aktuell[$feld] = stripcslashes($m[2]);
            } elseif ($feld !== null && preg_match('/^"(.*)"$/', $zeile, $m)) {
                $aktuell[$feld] .= stripcslashes($m[1]);
            }
        }
        $abschliessen();

        return $eintraege;
    }

    /** Liest eine .mo (GNU-Format) ohne den Kopfeintrag. @return array<string, string> */
    private static function mo(string $datei): array
    {
        self::assertFileExists($datei);
        $d = (string) file_get_contents($datei);
        $magie = unpack('V', substr($d, 0, 4))[1];
        $f = $magie === 0x950412de ? 'V' : 'N';
        [, $anzahl, $original, $uebersetzt] = array_values(unpack("{$f}rev/{$f}n/{$f}o/{$f}t", substr($d, 4, 16)));
        $ergebnis = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $o = unpack("{$f}l/{$f}p", substr($d, $original + 8 * $i, 8));
            $t = unpack("{$f}l/{$f}p", substr($d, $uebersetzt + 8 * $i, 8));
            $id = substr($d, $o['p'], $o['l']);
            if ($id !== '') {
                $ergebnis[$id] = substr($d, $t['p'], $t['l']);
            }
        }

        return $ergebnis;
    }
}
