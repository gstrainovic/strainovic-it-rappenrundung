<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Connectoren;
use StrainovicIT\Rappenrundung\Plugin;

final class ConnectorenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function sprachen(): iterable
    {
        yield 'de_CH' => ['de_CH', 'https://www.strainovic-it.ch/'];
        yield 'de_DE' => ['de_DE', 'https://www.strainovic-it.ch/'];
        yield 'fr_CH' => ['fr_CH', 'https://www.strainovic-it.ch/fr/'];
        yield 'it_IT' => ['it_IT', 'https://www.strainovic-it.ch/it/'];
        yield 'en_US' => ['en_US', 'https://www.strainovic-it.ch/en/'];
        yield 'es_ES' => ['es_ES', 'https://www.strainovic-it.ch/en/'];
    }

    #[DataProvider('sprachen')]
    public function testSeiteInDerSpracheDesAdmins(string $locale, string $basis): void
    {
        self::assertSame($basis . 'klara-shop-connector/', Connectoren::url('klara-shop-connector', $locale));
    }

    public function testLinksInDerPluginZeile(): void
    {
        Functions\when('determine_locale')->justReturn('fr_FR');

        $links = Connectoren::zeilenLinks(['Version 0.3.0'], 'strainovic-it-rappenrundung/strainovic-it-rappenrundung.php', 'strainovic-it-rappenrundung/strainovic-it-rappenrundung.php');

        self::assertCount(4, $links);
        self::assertSame('Version 0.3.0', $links[0]);
        self::assertStringContainsString('href="https://www.strainovic-it.ch/fr/klara-shop-connector/"', $links[1]);
        self::assertStringContainsString('KLARA shop connector', $links[1]);
        self::assertStringContainsString('href="https://www.strainovic-it.ch/fr/abaninja-shop-connector/"', $links[2]);
        self::assertStringContainsString('AbaNinja shop connector', $links[2]);
        self::assertStringContainsString('href="https://www.strainovic-it.ch/fr/spenden/"', $links[3]);
        self::assertStringContainsString('>Donate<', $links[3]);
    }

    public function testSpendenlinkInDerReadme(): void
    {
        $readme = (string) file_get_contents(__DIR__ . '/../plugin/strainovic-it-rappenrundung/readme.txt');

        self::assertMatchesRegularExpression('/^Donate link: https:\/\/www\.strainovic-it\.ch\/spenden\/$/m', $readme);
    }

    public function testFremdePluginZeilenBleibenUnveraendert(): void
    {
        $links = Connectoren::zeilenLinks(['Version 9.0'], 'woocommerce/woocommerce.php', 'strainovic-it-rappenrundung/strainovic-it-rappenrundung.php');

        self::assertSame(['Version 9.0'], $links);
    }

    public function testHinweisUnterDemEinstellungsfeld(): void
    {
        Functions\when('determine_locale')->justReturn('it_CH');

        $felder = Plugin::einstellungen([['id' => 'pricing_options', 'type' => 'sectionend']]);
        $desc = $felder[0]['desc'];

        self::assertStringContainsString('Label of the rounding line', $desc);
        self::assertStringContainsString('KLARA or AbaNinja', $desc);
        self::assertStringContainsString('href="https://www.strainovic-it.ch/it/klara-shop-connector/"', $desc);
        self::assertStringContainsString('href="https://www.strainovic-it.ch/it/abaninja-shop-connector/"', $desc);
    }
}
