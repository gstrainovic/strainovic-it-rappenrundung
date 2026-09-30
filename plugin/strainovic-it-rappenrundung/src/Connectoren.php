<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/**
 * Hinweis auf die kostenpflichtigen Connectoren für KLARA und AbaNinja, die dasselbe Publikum haben (CHF-Shops auf WooCommerce).
 * Nur an den Stellen, die wordpress.org erlaubt: Plugin-Zeile und das eigene Einstellungsfeld, kein Banner im Dashboard.
 */
final class Connectoren
{
    private const BASIS = 'https://www.strainovic-it.ch/';

    /** Produktseite in der Sprache des Admins; Deutsch ohne Präfix, sonst /fr/, /it/, /en/. */
    public static function url(string $seite, string $locale): string
    {
        $praefix = match (Sprache::datei($locale)) {
            'de_CH', 'de_DE' => '',
            'fr_FR' => 'fr/',
            'it_IT' => 'it/',
            default => 'en/',
        };

        return self::BASIS . $praefix . $seite . '/';
    }

    /**
     * Filter plugin_row_meta: Connectoren und Spendenseite unter der eigenen Zeile auf der Plugin-Seite.
     *
     * @param list<string> $links
     * @return list<string>
     */
    public static function zeilenLinks(array $links, string $datei, string $eigeneDatei): array
    {
        if ($datei !== $eigeneDatei) {
            return $links;
        }
        [$klara, $abaninja] = self::links();
        $links[] = $klara;
        $links[] = $abaninja;
        $links[] = self::link(self::url('spenden', determine_locale()), __('Donate', 'strainovic-it-rappenrundung'));

        return $links;
    }

    /** Satz unter dem Einstellungsfeld der Rundungsposition. */
    public static function hinweis(): string
    {
        [$klara, $abaninja] = self::links();

        /* translators: 1: link to the KLARA shop connector, 2: link to the AbaNinja shop connector */
        return sprintf(esc_html__('Using KLARA or AbaNinja? My connectors create every order there as an invoice automatically, with the same rounded total: %1$s, %2$s.', 'strainovic-it-rappenrundung'), $klara, $abaninja);
    }

    /** @return array{string, string} */
    private static function links(): array
    {
        $locale = determine_locale();

        return [
            self::link(self::url('klara-shop-connector', $locale), __('KLARA shop connector', 'strainovic-it-rappenrundung')),
            self::link(self::url('abaninja-shop-connector', $locale), __('AbaNinja shop connector', 'strainovic-it-rappenrundung')),
        ];
    }

    private static function link(string $url, string $text): string
    {
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text) . '</a>';
    }
}
