<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung;

/** Kaufmännische Rundung auf 5 Rappen, wie sie in der Schweiz für Rechnungen und Barzahlung gilt. */
final class Rundung
{
    /** Betrag, der zum Total addiert werden muss, damit es auf 0.05 aufgeht (negativ = abrunden). */
    public static function differenz(float $total): float
    {
        $ziel = round(round($total * 20) / 20, 2);

        return round($ziel - $total, 4) + 0.0;
    }

    public static function giltFuer(string $waehrung): bool
    {
        return strtoupper($waehrung) === 'CHF';
    }
}
