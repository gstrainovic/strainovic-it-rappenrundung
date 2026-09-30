<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Rundung;

final class RundungTest extends TestCase
{
    /** @return iterable<string, array{float, float}> */
    public static function faelle(): iterable
    {
        yield 'schon rund' => [29.90, 0.0];
        yield 'abrunden 1' => [29.91, -0.01];
        yield 'abrunden 2' => [29.92, -0.02];
        yield 'aufrunden 3' => [29.93, 0.02];
        yield 'aufrunden 4' => [29.94, 0.01];
        yield 'halb wird aufgerundet' => [10.025, 0.025];
        yield 'Gleitkomma 0.1 + 0.2' => [0.1 + 0.2, 0.0];
        yield 'grosser Betrag' => [12345.67, -0.02];
        yield 'null' => [0.0, 0.0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('faelle')]
    public function testDifferenzZumFuenfRappenBetrag(float $total, float $differenz): void
    {
        self::assertEqualsWithDelta($differenz, Rundung::differenz($total), 0.00001);
        self::assertEqualsWithDelta(0.0, fmod(round(($total + Rundung::differenz($total)) * 100), 5), 0.00001);
    }

    public function testNurFuerFranken(): void
    {
        self::assertTrue(Rundung::giltFuer('CHF'));
        self::assertFalse(Rundung::giltFuer('EUR'));
    }
}
