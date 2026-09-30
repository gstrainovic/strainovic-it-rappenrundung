<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/**
 * Rundet das Total des Warenkorbs auf 5 Rappen. Die Differenz steht als eigene Gebühr ohne MWST
 * im Warenkorb und in der Bestellung, damit Positionen, Total und Buchhaltung übereinstimmen.
 * Ablauf: nach jeder Berechnung die Differenz zum gerundeten Total bestimmen; weicht sie von der
 * aktuellen Rundungsgebühr ab, einmal neu rechnen. Das erfasst Gutscheine, Versand und fremde
 * Gebühren, egal in welcher Reihenfolge andere Plugins sie setzen.
 */
final class Warenkorb
{
    public const GEBUEHR = 'rappenrundung';

    /** @var callable(): string */
    private $waehrung;
    /** @var callable(): string erst beim Berechnen gelesen, wenn die Übersetzung geladen ist */
    private $bezeichnung;
    private float $differenz = 0.0;
    private bool $laeuft = false;

    public function __construct(callable $waehrung, callable $bezeichnung)
    {
        $this->waehrung = $waehrung;
        $this->bezeichnung = $bezeichnung;
    }

    public function registrieren(): void
    {
        add_action('woocommerce_cart_calculate_fees', [$this, 'gebuehr'], 999);
        add_action('woocommerce_after_calculate_totals', [$this, 'nachBerechnung'], 999);
    }

    /** @param \WC_Cart|object $cart */
    public function gebuehr(object $cart): void
    {
        if ($this->differenz != 0.0 && Rundung::giltFuer(($this->waehrung)())) {
            $cart->fees_api()->add_fee(['id' => self::GEBUEHR, 'name' => ($this->bezeichnung)(), 'amount' => $this->differenz, 'taxable' => false]);
        }
    }

    /** @param \WC_Cart|object $cart */
    public function nachBerechnung(object $cart): void
    {
        if ($this->laeuft || !Rundung::giltFuer(($this->waehrung)())) {
            return;
        }
        $fees = $cart->fees_api()->get_fees();
        $eigene = isset($fees[self::GEBUEHR]) ? (float) $fees[self::GEBUEHR]->amount : 0.0;
        $soll = Rundung::differenz(self::summeDerTeile($cart) - $eigene);
        if (abs($soll - $eigene) < 0.0001) {
            return;
        }
        $this->differenz = $soll;
        $this->laeuft = true;
        try {
            $cart->calculate_totals();
        } finally {
            $this->laeuft = false;
        }
    }

    /**
     * Total aus Artikeln, Versand, Gebühren und MWST, wie WooCommerce es vor dem Filter «woocommerce_calculated_total»
     * bildet. Nicht get_total(): Ein Snippet auf diesem Filter rundet nur die Anzeige; der Checkout-Block legt die
     * Bestellung aus den Teilen an, dort bliebe ohne eigene Rundungszeile der ungerundete Betrag.
     *
     * @param \WC_Cart|object $cart
     */
    private static function summeDerTeile(object $cart): float
    {
        return round((float) $cart->get_cart_contents_total() + (float) $cart->get_shipping_total()
            + (float) $cart->get_fee_total() + (float) $cart->get_total_tax(), 2);
    }
}
