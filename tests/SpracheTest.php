<?php
declare(strict_types=1);

namespace StrainovicIT\Rappenrundung\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StrainovicIT\Rappenrundung\Sprache;

final class SpracheTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function faelle(): iterable
    {
        yield 'de_CH' => ['de_CH', 'de_CH'];
        yield 'de_CH_informal' => ['de_CH_informal', 'de_CH'];
        yield 'de_DE' => ['de_DE', 'de_DE'];
        yield 'de_DE_formal' => ['de_DE_formal', 'de_DE'];
        yield 'de_AT' => ['de_AT', 'de_DE'];
        yield 'fr_FR' => ['fr_FR', 'fr_FR'];
        yield 'fr_BE' => ['fr_BE', 'fr_FR'];
        yield 'fr_CA' => ['fr_CA', 'fr_FR'];
        yield 'it_IT' => ['it_IT', 'it_IT'];
        yield 'it_CH' => ['it_CH', 'it_IT'];
        yield 'en_US' => ['en_US', null];
        yield 'en_GB' => ['en_GB', null];
        yield 'es_ES' => ['es_ES', null];
        yield 'leer' => ['', null];
        yield 'nur Sprache' => ['de', 'de_DE'];
    }

    #[DataProvider('faelle')]
    public function testSeitenspracheAufMitgelieferteDatei(string $locale, ?string $datei): void
    {
        self::assertSame($datei, Sprache::datei($locale));
    }
}
