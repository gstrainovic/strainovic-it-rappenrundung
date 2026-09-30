<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/**
 * Stellt die Rundungszeile in den Summen der Bestellung (Bestellmail, Danke-Seite, «Mein Konto», Rechnungs-Plugins,
 * die WC_Order::get_order_item_totals() nutzen) direkt vor das Total, wie auf Schweizer Rechnungen üblich.
 * WooCommerce ordnet Gebühren sonst vor der MWST ein. Die Seitenleiste des Checkout-Blocks bestimmt WooCommerce selbst.
 */
final class Reihenfolge
{
    /** Markiert die Rundungsposition der Bestellung; der Name taugt nicht, er ist einstellbar und übersetzt. */
    public const META = '_rappenrundung';

    public function registrieren(): void
    {
        add_action('woocommerce_checkout_create_order_fee_item', [$this, 'markieren'], 10, 3);
        add_filter('woocommerce_get_order_item_totals', [$this, 'sortieren'], 999, 2);
    }

    /**
     * Klassischer Checkout und Checkout-Block legen Gebühren über WC_Checkout::create_order_fee_lines() an.
     *
     * @param \WC_Order_Item_Fee|object $item
     * @param string $schluessel ID der Gebühr im Warenkorb
     */
    public function markieren(object $item, string $schluessel, ?object $gebuehr = null): void
    {
        if ($schluessel === Warenkorb::GEBUEHR) {
            $item->add_meta_data(self::META, '1', true);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $zeilen Schlüssel wie WooCommerce: fee_<Positions-ID>, order_total …
     * @param \WC_Order|object $order
     * @return array<string, array<string, mixed>>
     */
    public function sortieren(array $zeilen, object $order): array
    {
        if (!isset($zeilen['order_total'])) {
            return $zeilen;
        }
        $rundung = [];
        foreach ($order->get_fees() as $fee) {
            $schluessel = 'fee_' . $fee->get_id();
            if ($fee->get_meta(self::META) === '1' && isset($zeilen[$schluessel])) {
                $rundung[$schluessel] = $zeilen[$schluessel];
            }
        }
        if ($rundung === []) {
            return $zeilen;
        }
        $neu = [];
        foreach ($zeilen as $schluessel => $zeile) {
            if (isset($rundung[$schluessel])) {
                continue;
            }
            if ($schluessel === 'order_total') {
                $neu += $rundung;
            }
            $neu[$schluessel] = $zeile;
        }

        return $neu;
    }
}
