<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Reihenfolge;
use StrainovicIT\Rappenrundung\Warenkorb;

final class ReihenfolgeTest extends TestCase
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

    public function testRegistriertBeideHooks(): void
    {
        $r = new Reihenfolge();
        $r->registrieren();

        self::assertNotFalse(has_action('woocommerce_checkout_create_order_fee_item', [$r, 'markieren']));
        self::assertNotFalse(has_filter('woocommerce_get_order_item_totals', [$r, 'sortieren']));
    }

    public function testMarkiertNurDieEigeneGebuehr(): void
    {
        $eigene = new FakeFeeItem(7);
        $fremde = new FakeFeeItem(8);
        $r = new Reihenfolge();

        $r->markieren($eigene, Warenkorb::GEBUEHR, (object) ['id' => Warenkorb::GEBUEHR]);
        $r->markieren($fremde, 'verpackung', (object) ['id' => 'verpackung']);

        self::assertSame('1', $eigene->get_meta(Reihenfolge::META));
        self::assertSame('', $fremde->get_meta(Reihenfolge::META));
    }

    public function testRundungStehtDirektVorDemTotal(): void
    {
        // WooCommerce ordnet Gebühren vor der MWST ein; auf Schweizer Rechnungen steht die Rundung zuletzt vor dem Total.
        $rundung = new FakeFeeItem(7, ['_rappenrundung' => '1']);
        $verpackung = new FakeFeeItem(8);
        $order = new FakeOrder([$rundung, $verpackung]);
        $zeilen = [
            'cart_subtotal' => ['label' => 'Zwischensumme', 'value' => '60.13'],
            'shipping' => ['label' => 'Versand', 'value' => '12.00'],
            'fee_7' => ['label' => 'Rundung auf 5 Rappen', 'value' => '-0.02'],
            'fee_8' => ['label' => 'Verpackung', 'value' => '1.00'],
            'tax' => ['label' => 'MWST', 'value' => '5.84'],
            'payment_method' => ['label' => 'Zahlungsart', 'value' => 'Rechnung'],
            'order_total' => ['label' => 'Total', 'value' => '77.95'],
        ];

        $neu = (new Reihenfolge())->sortieren($zeilen, $order);

        self::assertSame(['cart_subtotal', 'shipping', 'fee_8', 'tax', 'payment_method', 'fee_7', 'order_total'], array_keys($neu));
        self::assertSame($zeilen['fee_7'], $neu['fee_7']);
    }

    public function testOhneRundungszeileUnveraendert(): void
    {
        $zeilen = ['cart_subtotal' => ['label' => 'a', 'value' => '1'], 'order_total' => ['label' => 'b', 'value' => '1']];

        self::assertSame($zeilen, (new Reihenfolge())->sortieren($zeilen, new FakeOrder([new FakeFeeItem(3)])));
    }

    public function testOhneTotalzeileUnveraendert(): void
    {
        $zeilen = ['fee_7' => ['label' => 'Rundung', 'value' => '-0.02'], 'tax' => ['label' => 'MWST', 'value' => '1']];

        self::assertSame($zeilen, (new Reihenfolge())->sortieren($zeilen, new FakeOrder([new FakeFeeItem(7, ['_rappenrundung' => '1'])])));
    }
}

/** Gebührenposition einer Bestellung (WC_Order_Item_Fee) mit Metadaten. */
final class FakeFeeItem
{
    /** @param array<string, string> $meta */
    public function __construct(private int $id, private array $meta = []) {}

    public function get_id(): int { return $this->id; }

    public function get_meta(string $key): string { return $this->meta[$key] ?? ''; }

    public function add_meta_data(string $key, string $wert, bool $unique = false): void { $this->meta[$key] = $wert; }
}

final class FakeOrder
{
    /** @param list<FakeFeeItem> $fees */
    public function __construct(private array $fees) {}

    /** @return list<FakeFeeItem> */
    public function get_fees(): array { return $this->fees; }
}
