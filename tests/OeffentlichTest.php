<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Das öffentliche Repo gstrainovic/strainovic-it-rappenrundung spiegelt nur den Code (bin-oeffentlich.sh).
 * Kundenfäden, Aufgaben und interne Notizen bleiben in diesem privaten Repo.
 */
final class OeffentlichTest extends TestCase
{
    private const WURZEL = __DIR__ . '/..';

    protected function setUp(): void
    {
        if (!is_file(self::WURZEL . '/oeffentlich/pfade.txt')) {
            self::markTestSkipped('öffentlicher Spiegel: Prüfung läuft nur im privaten Repo');
        }
    }

    /** @return list<string> */
    private static function pfade(): array
    {
        $zeilen = file(self::WURZEL . '/oeffentlich/pfade.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter($zeilen, static fn (string $z): bool => !str_starts_with($z, '#')));
    }

    public function testCodeTestsUndUebersetzungenSindOeffentlich(): void
    {
        foreach (['plugin', 'tests', 'e2e', 'translations', 'composer.json', 'composer.lock', 'phpunit.xml', 'bin-test.sh'] as $pfad) {
            self::assertContains($pfad, self::pfade());
        }
    }

    public function testPrivatesBleibtPrivat(): void
    {
        foreach (self::pfade() as $pfad) {
            self::assertFileExists(self::WURZEL . '/' . $pfad, "$pfad fehlt");
            self::assertDoesNotMatchRegularExpression('/versand|todo|AGENTS|CLAUDE|\.zip$|vendor/i', $pfad);
        }
    }

    public function testOeffentlichesReadmeUndLizenz(): void
    {
        $readme = (string) file_get_contents(self::WURZEL . '/oeffentlich/README.md');
        self::assertStringContainsString('https://wordpress.org/plugins/strainovic-it-rappenrundung/', $readme);
        self::assertStringContainsString('GPL', $readme);
        self::assertStringContainsString('GNU GENERAL PUBLIC LICENSE', (string) file_get_contents(self::WURZEL . '/oeffentlich/LICENSE'));
    }

    public function testKeineKundennamenImOeffentlichenCode(): void
    {
        // Namen aus einer privaten Datei, sonst stünden sie in diesem Test selbst im Spiegel
        $namen = file(self::WURZEL . '/oeffentlich/nicht-oeffentlich.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        self::assertNotEmpty($namen);
        $kunden = '/' . implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), $namen)) . '/i';
        foreach (self::pfade() as $pfad) {
            $dateien = is_dir(self::WURZEL . '/' . $pfad)
                ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::WURZEL . '/' . $pfad, \FilesystemIterator::SKIP_DOTS))
                : [new \SplFileInfo(self::WURZEL . '/' . $pfad)];
            foreach ($dateien as $datei) {
                if (str_contains($datei->getPathname(), '/node_modules/')) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression($kunden, (string) file_get_contents($datei->getPathname()), $datei->getPathname());
            }
        }
    }
}
