<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Warenkorb;

final class WarenkorbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRegistriertBeideHooksSpaet(): void
    {
        $w = new Warenkorb(fn () => 'CHF', fn () => 'Rounding to 5 cents');
        $w->registrieren();

        self::assertSame(999, has_action('woocommerce_cart_calculate_fees', [$w, 'gebuehr']));
        self::assertSame(999, has_action('woocommerce_after_calculate_totals', [$w, 'nachBerechnung']));
    }

    public function testAbrundenMitGebuehrOhneMwst(): void
    {
        $cart = new FakeCart(29.92, $w = new Warenkorb(fn () => 'CHF', fn () => 'Rounding to 5 cents'));
        $cart->calculate_totals();

        self::assertSame(29.90, $cart->total);
        $f = $cart->fees[Warenkorb::GEBUEHR];
        self::assertSame(['Rounding to 5 cents', -0.02, false], [$f->name, $f->amount, $f->taxable]);
        self::assertSame(2, $cart->berechnungen);
    }

    public function testBezeichnungErstBeimBerechnenLesen(): void
    {
        // Übersetzungen laden erst auf «init»; die Bezeichnung darf nicht schon beim Registrieren feststehen.
        $sprache = 'Rounding to 5 cents';
        $cart = new FakeCart(29.92, new Warenkorb(fn () => 'CHF', function () use (&$sprache): string { return $sprache; }));
        $sprache = 'Arrondi à 5 centimes';
        $cart->calculate_totals();

        self::assertSame('Arrondi à 5 centimes', $cart->fees[Warenkorb::GEBUEHR]->name);
    }

    public function testAufrunden(): void
    {
        $cart = new FakeCart(29.93, new Warenkorb(fn () => 'CHF', fn () => 'Rundung'));
        $cart->calculate_totals();

        self::assertSame(29.95, $cart->total);
        self::assertSame(0.02, $cart->fees[Warenkorb::GEBUEHR]->amount);
    }

    public function testRundesTotalOhneGebuehrUndOhneZweiteBerechnung(): void
    {
        $cart = new FakeCart(29.90, new Warenkorb(fn () => 'CHF', fn () => 'Rundung'));
        $cart->calculate_totals();

        self::assertSame([], $cart->fees);
        self::assertSame(1, $cart->berechnungen);
    }

    public function testWarenkorbAendertSichImSelbenAufruf(): void
    {
        $cart = new FakeCart(29.92, new Warenkorb(fn () => 'CHF', fn () => 'Rundung'));
        $cart->calculate_totals();
        $cart->basis = 40.00;
        $cart->calculate_totals();

        self::assertSame(40.00, $cart->total);
        self::assertArrayNotHasKey(Warenkorb::GEBUEHR, $cart->fees);

        $cart->basis = 40.04;
        $cart->calculate_totals();
        self::assertSame(40.05, $cart->total);
    }

    public function testFremderFilterAufDemTotalVerhindertDieRundungszeileNicht(): void
    {
        // Snippet wie «round_price_product» auf woocommerce_calculated_total: rundet nur das angezeigte Total.
        // Der Checkout-Block legt die Bestellung aus den Teilen an; ohne eigene Zeile bliebe dort 77.97.
        $cart = new FakeCart(77.97, new Warenkorb(fn () => 'CHF', fn () => 'Rundung'));
        $cart->filter = fn (float $t): float => round(($t + 0.000001) * 20) / 20;
        $cart->calculate_totals();

        self::assertSame(-0.02, $cart->fees[Warenkorb::GEBUEHR]->amount ?? null);
        self::assertSame(77.95, round($cart->basis + $cart->get_fee_total(), 2));
    }

    public function testAndereWaehrungBleibtUnberuehrt(): void
    {
        $cart = new FakeCart(29.92, new Warenkorb(fn () => 'EUR', fn () => 'Rundung'));
        $cart->calculate_totals();

        self::assertSame(29.92, $cart->total);
        self::assertSame([], $cart->fees);
    }
}
