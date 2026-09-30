<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

final class Plugin
{
    public const VERSION = '0.4.3';
    public const OPTION = 'rappenrundung_bezeichnung';

    /** Standardwerte früherer Versionen; stehen sie in der Datenbank, gilt der übersetzte Standard. */
    private const ALTE_STANDARDS = ['Rundungsdifferenz'];

    private static bool $gestartet = false;

    /**
     * Sind 0.3.x (Ordner «rappenrundung») und ab 0.4.0 (Ordner «strainovic-it-rappenrundung») gleichzeitig aktiv,
     * teilen sie sich diese Klasse; nur der erste Start registriert die Hooks, sonst käme die Rundung doppelt.
     *
     * @param string $datei Basisname der Plugin-Datei (plugin_basename), für die Links in der eigenen Plugin-Zeile
     */
    public static function starten(string $datei = ''): void
    {
        if (self::$gestartet || !class_exists('WooCommerce')) {
            return;
        }
        self::$gestartet = true;
        (new Warenkorb('get_woocommerce_currency', [self::class, 'bezeichnung']))->registrieren();
        (new Reihenfolge())->registrieren();
        add_filter('woocommerce_general_settings', [self::class, 'einstellungen']);
        add_filter('plugin_row_meta', static fn (array $links, string $plugin): array => Connectoren::zeilenLinks($links, $plugin, $datei), 10, 2);
    }

    public static function bezeichnung(): string
    {
        $wert = trim((string) get_option(self::OPTION, ''));

        return $wert !== '' && !in_array($wert, self::ALTE_STANDARDS, true) ? $wert : self::standard();
    }

    private static function standard(): string
    {
        return __('Rounding to 5 cents', 'strainovic-it-rappenrundung');
    }

    /**
     * Feld für die Bezeichnung der Rundungsposition, unter «Allgemein → Währungsoptionen».
     * Leer = übersetzter Standard; er steht nur als Platzhalter im Feld, damit Speichern ihn nicht in einer Sprache festhält.
     *
     * @param list<array<string, mixed>> $felder
     * @return list<array<string, mixed>>
     */
    public static function einstellungen(array $felder): array
    {
        $neu = [
            'id' => self::OPTION,
            'type' => 'text',
            'title' => __('Rounding line', 'strainovic-it-rappenrundung'),
            'desc' => esc_html__('Label of the rounding line in the cart and in the order. Leave empty to use the default in the language of the site. Only applies when the shop currency is CHF.', 'strainovic-it-rappenrundung')
                . '<br>' . Connectoren::hinweis(),
            'default' => '',
            'placeholder' => self::standard(),
            'desc_tip' => false,
        ];
        $ergebnis = [];
        $eingefuegt = false;
        foreach ($felder as $feld) {
            if (!$eingefuegt && ($feld['id'] ?? '') === 'pricing_options' && ($feld['type'] ?? '') === 'sectionend') {
                $ergebnis[] = $neu;
                $eingefuegt = true;
            }
            $ergebnis[] = $feld;
        }
        if (!$eingefuegt) {
            $ergebnis[] = $neu;
        }

        return $ergebnis;
    }
}
