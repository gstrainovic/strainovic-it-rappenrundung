<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Plugin;

final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('determine_locale')->justReturn('de_CH');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testStartetNurEinmalAuchWennDieAlteVersionNochAktivIst(): void
    {
        // 0.3.x lag im Ordner «rappenrundung», ab 0.4.0 in «strainovic-it-rappenrundung». Sind beide aktiv, teilen sie
        // sich die Klassen; ein zweiter Start würde die Hooks doppelt registrieren und die Rundung doppelt setzen.
        if (!class_exists('WooCommerce', false)) {
            eval('final class WooCommerce {}');
        }
        Functions\when('get_woocommerce_currency')->justReturn('CHF');
        \Brain\Monkey\Actions\expectAdded('woocommerce_cart_calculate_fees')->once();
        \Brain\Monkey\Actions\expectAdded('woocommerce_checkout_create_order_fee_item')->once();
        \Brain\Monkey\Filters\expectAdded('woocommerce_get_order_item_totals')->once();

        Plugin::starten('strainovic-it-rappenrundung/strainovic-it-rappenrundung.php');
        Plugin::starten('rappenrundung/rappenrundung.php');
        self::assertTrue(true);
    }

    public function testEinstellungStehtNachDerWaehrungsposition(): void
    {
        $felder = Plugin::einstellungen([
            ['id' => 'pricing_options', 'type' => 'title'],
            ['id' => 'woocommerce_currency', 'type' => 'select'],
            ['id' => 'woocommerce_price_num_decimals', 'type' => 'number'],
            ['id' => 'pricing_options', 'type' => 'sectionend'],
        ]);

        self::assertSame(['pricing_options', 'woocommerce_currency', 'woocommerce_price_num_decimals', Plugin::OPTION, 'pricing_options'], array_column($felder, 'id'));
        $feld = $felder[3];
        self::assertSame('text', $feld['type']);
        // Kein gespeicherter Standard: sonst hielte das Speichern der Einstellungen die Sprache des Admins fest.
        self::assertSame('', $feld['default']);
        self::assertSame('Rounding to 5 cents', $feld['placeholder']);
    }

    public function testBezeichnungFaelltAufStandard(): void
    {
        Functions\when('get_option')->justReturn('   ');
        self::assertSame('Rounding to 5 cents', Plugin::bezeichnung());

        Functions\when('get_option')->justReturn('Rundung 5 Rp.');
        self::assertSame('Rundung 5 Rp.', Plugin::bezeichnung());
    }

    public function testStandardFolgtDerSeitensprache(): void
    {
        Functions\when('__')->alias(static fn (string $text): string => $text === 'Rounding to 5 cents' ? 'Arrondi à 5 centimes' : $text);
        Functions\when('get_option')->justReturn('');

        self::assertSame('Arrondi à 5 centimes', Plugin::bezeichnung());
    }

    public function testGespeicherterStandardAus010BlockiertDieUebersetzungNicht(): void
    {
        // 0.1.0 hatte «Rundungsdifferenz» als Standard im Feld; wer die Einstellungen speicherte, hat ihn in der Datenbank.
        Functions\when('__')->alias(static fn (string $text): string => $text === 'Rounding to 5 cents' ? 'Arrotondamento a 5 centesimi' : $text);
        Functions\when('get_option')->justReturn('Rundungsdifferenz');

        self::assertSame('Arrotondamento a 5 centesimi', Plugin::bezeichnung());
    }

    public function testKeinEigenerUebersetzungsladerUndKeineMitgeliefertenDateien(): void
    {
        // wordpress.org verlangt: Übersetzungen kommen als Sprachpakete von translate.wordpress.org, die WordPress
        // selbst lädt; das Plugin liefert keine .po/.mo mit und lädt keine eigenen.
        self::assertFalse(method_exists(Plugin::class, 'textdomainLaden'));
        self::assertSame([], glob(dirname(__DIR__) . '/plugin/strainovic-it-rappenrundung/languages/*.{po,mo}', GLOB_BRACE) ?: []);
    }
}
