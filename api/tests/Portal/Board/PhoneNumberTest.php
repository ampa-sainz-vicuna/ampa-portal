<?php

declare(strict_types=1);

namespace App\Tests\Portal\Board;

use App\Portal\Domain\Board\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    #[Test]
    #[DataProvider('valid')]
    public function acepta_nueve_cifras_o_prefijo_internacional_y_lo_normaliza(string $typed, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::fromString($typed)->getValue());
    }

    /** @return iterable<string, array{string, string}> */
    public static function valid(): iterable
    {
        yield 'nueve cifras' => ['600123456', '600123456'];
        yield 'con espacios' => ['600 12 34 56', '600123456'];
        yield 'con guiones y puntos' => ['600-123.456', '600123456'];
        yield 'con prefijo de España' => ['+34 600 123 456', '+34600123456'];
        yield 'internacional' => ['+44 20 7946 0958', '+442079460958'];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function rechaza_lo_que_no_es_un_telefono(string $typed): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PhoneNumber::fromString($typed);
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'ocho cifras sin prefijo' => ['60012345'];
        yield 'diez cifras sin prefijo' => ['6001234567'];
        yield 'letras' => ['60012345a'];
        yield 'más con demasiadas cifras' => ['+1234567890123456'];
        yield 'más con pocas cifras' => ['+1234567'];
        yield 'vacío' => [''];
    }
}
