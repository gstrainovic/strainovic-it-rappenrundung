<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/**
 * Stellt die Rundungszeile im klassischen Warenkorb und in der klassischen Kasse direkt vor das Total, wie
 * `Reihenfolge` es für die Summen der Bestellung tut. Die Vorlagen von WooCommerce (cart/cart-totals.php,
 * checkout/review-order.php) geben Gebühren vor der MWST aus und haben dafür keinen Filter: Darum nimmt diese
 * Klasse die Rundung für die Dauer der Ausgabe aus der Gebührenliste und schreibt die Zeile im Hook vor dem Total.
 * Die berechneten Summen bleiben unberührt. Warenkorb- und Checkout-Block ordnet WooCommerce selbst.
 */
final class Summenzeile
{
    /** @var array<int|string, object>|null Gebührenliste vor dem Ausblenden, null wenn nichts ausgeblendet ist */
    private ?array $alle = null;
    private ?object $rundung = null;

    public function registrieren(): void
    {
        add_action('woocommerce_before_cart_totals', [$this, 'ausblenden']);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'zeileImWarenkorb']);
        add_action('woocommerce_after_cart_totals', [$this, 'zuruecklegen']);
        add_action('woocommerce_review_order_before_cart_contents', [$this, 'ausblenden']);
        add_action('woocommerce_review_order_before_order_total', [$this, 'zeileInDerKasse']);
        add_action('woocommerce_review_order_after_order_total', [$this, 'zuruecklegen']);
    }

    public function ausblenden(): void
    {
        $api = $this->gebuehren();
        if ($api === null || $this->alle !== null) {
            return;
        }
        $alle = $api->get_fees();
        if (!isset($alle[Warenkorb::GEBUEHR])) {
            return;
        }
        $this->alle = $alle;
        $this->rundung = $alle[Warenkorb::GEBUEHR];
        unset($alle[Warenkorb::GEBUEHR]);
        $api->set_fees($alle);
    }

    public function zeileImWarenkorb(): void
    {
        $this->zeile(true);
    }

    public function zeileInDerKasse(): void
    {
        $this->zeile(false);
    }

    /** Legt die Rundung wieder in die Gebührenliste; auch dann, wenn eine Vorlage den Hook vor dem Total auslässt. */
    public function zuruecklegen(): void
    {
        $api = $this->gebuehren();
        if ($api !== null && $this->alle !== null) {
            $api->set_fees($this->alle);
        }
        $this->alle = null;
        $this->rundung = null;
    }

    private function zeile(bool $mitTitel): void
    {
        $fee = $this->rundung;
        if ($fee === null) {
            return;
        }
        $this->zuruecklegen();
        echo '<tr class="fee"><th>' . esc_html($fee->name) . '</th><td'
            . ($mitTitel ? ' data-title="' . esc_attr($fee->name) . '"' : '') . '>';
        wc_cart_totals_fee_html($fee);
        echo '</td></tr>';
    }

    private function gebuehren(): ?object
    {
        $cart = function_exists('WC') ? (WC()->cart ?? null) : null;

        return is_object($cart) ? $cart->fees_api() : null;
    }
}
