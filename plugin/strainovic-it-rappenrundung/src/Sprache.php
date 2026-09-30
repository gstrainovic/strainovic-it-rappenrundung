<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/** Bildet die Sprache der Seite auf eine der vier Sprachen ab (de_CH, de_DE, fr_FR, it_IT), für die Sprache der Connector-Links. */
final class Sprache
{
    /** @return string|null null = keine Datei, die englischen Quelltexte gelten */
    public static function datei(string $locale): ?string
    {
        if ($locale === 'de_CH' || str_starts_with($locale, 'de_CH_')) {
            return 'de_CH';
        }

        return match (explode('_', $locale)[0]) {
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'it' => 'it_IT',
            default => null,
        };
    }
}
