<?php

declare(strict_types=1);

namespace App\Tests\Portal\Board;

use App\Portal\Domain\Board\IdentityDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityDocumentTest extends TestCase
{
    #[Test]
    #[DataProvider('valid')]
    public function acepta_dni_y_nie_con_la_letra_correcta_y_los_normaliza(string $typed, string $expected): void
    {
        self::assertSame($expected, IdentityDocument::fromString($typed)->getValue());
    }

    /** @return iterable<string, array{string, string}> */
    public static function valid(): iterable
    {
        yield 'DNI' => ['12345678Z', '12345678Z'];
        yield 'DNI en minúsculas, con puntos y guion' => ['12.345.678-z', '12345678Z'];
        yield 'DNI con espacios' => [' 12345678 Z ', '12345678Z'];
        yield 'NIE con X' => ['X1234567L', 'X1234567L'];
        yield 'NIE con Y' => ['y-1234567-x', 'Y1234567X'];
        yield 'NIE con Z' => ['Z1234567R', 'Z1234567R'];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function rechaza_letras_mal_formas_y_longitudes(string $typed): void
    {
        $this->expectException(\InvalidArgumentException::class);

        IdentityDocument::fromString($typed);
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'letra de control equivocada' => ['12345678A'];
        yield 'NIE con letra equivocada' => ['X1234567A'];
        yield 'sin letra' => ['12345678'];
        yield 'muy corto' => ['1234567Z'];
        yield 'letra inicial que no es X, Y ni Z' => ['A1234567L'];
        yield 'vacío' => [''];
    }

    #[Test]
    public function el_error_no_lleva_el_documento_dentro(): void
    {
        try {
            IdentityDocument::fromString('12345678A');
            self::fail('Tenía que fallar.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringNotContainsString('12345678', $e->getMessage());
        }
    }

    #[Test]
    public function dos_escrituras_del_mismo_documento_son_iguales(): void
    {
        self::assertTrue(IdentityDocument::fromString('12345678z')->equals(IdentityDocument::fromString('12-345-678 Z')));
    }
}
