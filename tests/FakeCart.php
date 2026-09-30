<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use StrainovicIT\Rappenrundung\Warenkorb;

/**
 * Warenkorb wie WC_Cart::calculate_totals: Gebühren neu sammeln, Total aus den Teilen bilden, durch den Filter
 * «woocommerce_calculated_total» geben (hier $filter), danach der Hook. $basis steht für Artikel, Versand und MWST.
 */
final class FakeCart
{
    /** @var array<string, object> */
    public array $fees = [];
    public float $total = 0.0;
    public int $berechnungen = 0;
    /** @var (callable(float): float)|null fremder Filter auf «woocommerce_calculated_total» */
    public $filter = null;

    public function __construct(public float $basis, private Warenkorb $w) {}

    public function calculate_totals(): void
    {
        $this->berechnungen++;
        $this->fees = [];
        $this->w->gebuehr($this);
        $roh = round($this->basis + $this->get_fee_total(), 2);
        $this->total = $this->filter ? ($this->filter)($roh) : $roh;
        $this->w->nachBerechnung($this);
    }

    public function get_total(string $context = 'view'): float { return $this->total; }

    public function get_cart_contents_total(): float { return $this->basis; }

    public function get_shipping_total(): float { return 0.0; }

    public function get_total_tax(): float { return 0.0; }

    public function get_fee_total(): float { return array_sum(array_map(fn ($f) => (float) $f->amount, $this->fees)); }

    public function fees_api(): object
    {
        $cart = $this;

        return new class ($cart) {
            public function __construct(private FakeCart $cart) {}
            public function add_fee(array $a): object { $f = (object) $a; $this->cart->fees[$a['id']] = $f; return $f; }
            public function get_fees(): array { return $this->cart->fees; }
        };
    }
}
