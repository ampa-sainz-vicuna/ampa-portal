<?php

declare(strict_types=1);

namespace App\Tests\Portal\Help;

use App\Portal\Application\Help\FaqFile;
use App\Portal\Domain\Help\FaqError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La forma del fichero faq.json. Las reglas de cada valor, en FaqTest.
 */
final class FaqFileTest extends TestCase
{
    private const array ENTRY = [
        'id' => 'facturacion-cerrar-mes',
        'application' => 'facturacion',
        'roles' => [],
        'question' => '¿Cómo cierro el mes?',
        'answer' => "Primero…\nDespués…",
        'keywords' => ['cierre'],
        'manual' => '06',
    ];

    #[Test]
    public function lee_el_fichero_entero(): void
    {
        $file = FaqFile::parse([
            'version' => 1,
            'updatedAt' => '2026-09-28',
            'notebookUrl' => 'https://notebooklm.example.com/cuaderno',
            'contactEmail' => 'ayuda@example.com',
            'entries' => [self::ENTRY],
        ]);

        self::assertSame('2026-09-28', $file->getUpdatedAt());
        self::assertSame('https://notebooklm.example.com/cuaderno', $file->getNotebookUrl());
        self::assertSame('ayuda@example.com', $file->getContactEmail());
        self::assertSame([self::ENTRY], array_map(static fn ($entry): array => $entry->toArray(), $file->getEntries()));
    }

    #[Test]
    public function roles_palabras_clave_manual_y_los_datos_generales_son_opcionales(): void
    {
        $file = FaqFile::parse(['version' => 1, 'entries' => [['id' => 'a', 'application' => 'general', 'question' => 'P', 'answer' => 'R']]]);

        self::assertNull($file->getNotebookUrl());
        self::assertSame(['id' => 'a', 'application' => 'general', 'roles' => [], 'question' => 'P', 'answer' => 'R', 'keywords' => [], 'manual' => null], $file->getEntries()[0]->toArray());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badFiles(): iterable
    {
        yield 'no es un objeto' => [[1, 2], 'no tiene la forma de faq.json'];
        yield 'sin versión' => [['entries' => []], 'otra versión'];
        yield 'otra versión' => [['version' => 2, 'entries' => []], 'otra versión ("version": 2)'];
        yield 'sin preguntas' => [['version' => 1], 'Falta la lista de preguntas'];
        yield 'una pregunta que no es un objeto' => [['version' => 1, 'entries' => ['hola']], 'La pregunta número 1 no es un objeto'];
        yield 'sin respuesta, con su id' => [['version' => 1, 'entries' => [[...self::ENTRY, 'answer' => null]]], '"facturacion-cerrar-mes": falta "answer"'];
        yield 'sin id, con su posición' => [['version' => 1, 'entries' => [self::ENTRY, [...self::ENTRY, 'id' => 7]]], 'La pregunta número 2: falta "id"'];
        yield 'roles que no son una lista' => [['version' => 1, 'entries' => [[...self::ENTRY, 'roles' => 'admin']]], '"roles" tiene que ser una lista'];
        yield 'palabras clave que no son texto' => [['version' => 1, 'entries' => [[...self::ENTRY, 'keywords' => [3]]]], '"keywords" tiene que ser una lista'];
        yield 'manual que no es texto' => [['version' => 1, 'entries' => [[...self::ENTRY, 'manual' => 6]]], '"manual" tiene que ser texto'];
        yield 'cuaderno que no es texto' => [['version' => 1, 'entries' => [], 'notebookUrl' => true], '"notebookUrl"'];
    }

    #[Test]
    #[DataProvider('badFiles')]
    public function un_fichero_mal_hecho_dice_donde(mixed $file, string $message): void
    {
        $this->expectException(FaqError::class);
        $this->expectExceptionMessage($message);

        FaqFile::parse($file);
    }
}
