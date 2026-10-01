<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Summenzeile;
use StrainovicIT\Rappenrundung\Warenkorb;

final class SummenzeileTest extends TestCase
{
    /** @var array<string, object> Gebühren des Warenkorbs, wie WC_Cart_Fees sie hält */
    private array $fees;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubEscapeFunctions();
        Functions\when('wc_cart_totals_fee_html')->alias(static function (object $fee): void {
            echo '<span class="amount">' . number_format((float) $fee->total, 2) . '</span>';
        });
        $this->fees = [];
        $test = $this;
        $api = new class ($test) {
            public function __construct(private SummenzeileTest $t) {}
            public function get_fees(): array { return $this->t->gebuehren(); }
            public function set_fees(array $fees): void { $this->t->setze($fees); }
        };
        $cart = new class ($api) {
            public function __construct(private object $api) {}
            public function fees_api(): object { return $this->api; }
        };
        Functions\when('WC')->justReturn((object) ['cart' => $cart]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @return array<string, object> */
    public function gebuehren(): array { return $this->fees; }

    /** @param array<int|string, object> $fees */
    public function setze(array $fees): void
    {
        $this->fees = [];
        foreach ($fees as $fee) {
            $this->fees[$fee->id] = $fee;
        }
    }

    private function gebuehr(string $id, string $name, float $betrag): object
    {
        return (object) ['id' => $id, 'name' => $name, 'amount' => $betrag, 'total' => $betrag, 'taxable' => false, 'tax' => 0.0];
    }

    private function ausgabe(callable $aufruf): string
    {
        ob_start();
        $aufruf();

        return (string) ob_get_clean();
    }

    public function testRegistriertFuerKlassischenWarenkorbUndKlassischeKasse(): void
    {
        $s = new Summenzeile();
        $s->registrieren();

        self::assertNotFalse(has_action('woocommerce_before_cart_totals', [$s, 'ausblenden']));
        self::assertNotFalse(has_action('woocommerce_cart_totals_before_order_total', [$s, 'zeileImWarenkorb']));
        self::assertNotFalse(has_action('woocommerce_after_cart_totals', [$s, 'zuruecklegen']));
        self::assertNotFalse(has_action('woocommerce_review_order_before_cart_contents', [$s, 'ausblenden']));
        self::assertNotFalse(has_action('woocommerce_review_order_before_order_total', [$s, 'zeileInDerKasse']));
        self::assertNotFalse(has_action('woocommerce_review_order_after_order_total', [$s, 'zuruecklegen']));
    }

    public function testNimmtDieRundungAusDerGebuehrenlisteUndZeigtSieVorDemTotal(): void
    {
        // WooCommerce gibt Gebühren vor der MWST aus; die Rundung gehört wie auf der Rechnung direkt vor das Total.
        $this->setze([$this->gebuehr('verpackung', 'Verpackung', 1.0), $this->gebuehr(Warenkorb::GEBUEHR, 'Rundung auf 5 Rappen', -0.02)]);
        $s = new Summenzeile();

        $s->ausblenden();
        self::assertSame(['verpackung'], array_keys($this->fees));

        $html = $this->ausgabe([$s, 'zeileImWarenkorb']);
        self::assertSame('<tr class="fee"><th>Rundung auf 5 Rappen</th><td data-title="Rundung auf 5 Rappen"><span class="amount">-0.02</span></td></tr>', $html);
        self::assertSame(['verpackung', Warenkorb::GEBUEHR], array_keys($this->fees));
    }

    public function testKassenzeileOhneDataTitleWieDieVorlageVonWooCommerce(): void
    {
        $this->setze([$this->gebuehr(Warenkorb::GEBUEHR, 'Rounding to 5 cents', 0.02)]);
        $s = new Summenzeile();
        $s->ausblenden();

        self::assertSame('<tr class="fee"><th>Rounding to 5 cents</th><td><span class="amount">0.02</span></td></tr>',
            $this->ausgabe([$s, 'zeileInDerKasse']));
        self::assertSame([Warenkorb::GEBUEHR], array_keys($this->fees));
    }

    public function testOhneRundungBleibtAllesWieEsIst(): void
    {
        $this->setze([$this->gebuehr('verpackung', 'Verpackung', 1.0)]);
        $s = new Summenzeile();

        $s->ausblenden();
        self::assertSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
        $s->zuruecklegen();
        self::assertSame(['verpackung'], array_keys($this->fees));
    }

    public function testZeileNurEinmalUndNieOhneVorherigesAusblenden(): void
    {
        $this->setze([$this->gebuehr(Warenkorb::GEBUEHR, 'Rundung', -0.02)]);
        $s = new Summenzeile();

        self::assertSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
        $s->ausblenden();
        self::assertNotSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
        self::assertSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
        self::assertCount(1, $this->fees);
    }

    public function testVorlageOhneHookVorDemTotalLegtDieGebuehrTrotzdemZurueck(): void
    {
        // Ein Theme mit eigener Vorlage ruft den Hook vor dem Total vielleicht nicht auf; die Gebühr darf nicht fehlen.
        $this->setze([$this->gebuehr(Warenkorb::GEBUEHR, 'Rundung', -0.02), $this->gebuehr('verpackung', 'Verpackung', 1.0)]);
        $s = new Summenzeile();

        $s->ausblenden();
        $s->zuruecklegen();

        self::assertEqualsCanonicalizing([Warenkorb::GEBUEHR, 'verpackung'], array_keys($this->fees));
        self::assertSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
    }

    public function testBezeichnungWirdMaskiert(): void
    {
        Functions\when('esc_html')->alias(static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES));
        Functions\when('esc_attr')->alias(static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES));
        $this->setze([$this->gebuehr(Warenkorb::GEBUEHR, 'Rundung <b>"5"</b>', -0.02)]);
        $s = new Summenzeile();
        $s->ausblenden();

        $html = $this->ausgabe([$s, 'zeileImWarenkorb']);
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringContainsString('Rundung &lt;b&gt;&quot;5&quot;&lt;/b&gt;', $html);
    }

    public function testOhneWarenkorbGeschiehtNichts(): void
    {
        Functions\when('WC')->justReturn((object) ['cart' => null]);
        $s = new Summenzeile();

        $s->ausblenden();
        self::assertSame('', $this->ausgabe([$s, 'zeileImWarenkorb']));
        $s->zuruecklegen();
        self::assertTrue(true);
    }
}
