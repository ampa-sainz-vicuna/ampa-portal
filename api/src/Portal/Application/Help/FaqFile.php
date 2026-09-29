<?php

declare(strict_types=1);

namespace App\Portal\Application\Help;

use App\Portal\Domain\Help\FaqEntry;
use App\Portal\Domain\Help\FaqError;

/**
 * El fichero faq.json, ya leído como JSON, comprobado campo a campo:
 *
 *   {"version": 1, "updatedAt": "2026-09-28", "notebookUrl": "https://…", "contactEmail": "…",
 *    "entries": [{"id": "facturacion-cerrar-mes", "application": "facturacion", "roles": [],
 *                 "question": "…", "answer": "…", "keywords": ["…"], "manual": "06"}]}
 *
 * Aquí solo la FORMA (que cada campo sea lo que tiene que ser). Los valores
 * los comprueban FaqEntry y Faq, que conocen las reglas. Los errores dicen en
 * qué pregunta está el problema, porque el fichero se escribe a mano (o lo
 * genera otra herramienta) y quien lo carga tiene que poder arreglarlo.
 *
 * `roles`, `keywords`, `manual`, `updatedAt`, `notebookUrl` y `contactEmail`
 * son opcionales; `version` y `entries`, no.
 */
final readonly class FaqFile
{
    /** La única versión del formato que se entiende. Si cambia, se sube aquí y en quien lo genera. */
    public const int VERSION = 1;

    /**
     * @param list<FaqEntry> $entries
     */
    private function __construct(
        private array $entries,
        private ?string $notebookUrl,
        private ?string $contactEmail,
        private ?string $updatedAt,
    ) {
    }

    /**
     * @throws FaqError
     */
    public static function parse(mixed $file): self
    {
        if (!is_array($file) || array_is_list($file)) {
            throw new FaqError('El fichero no tiene la forma de faq.json: tiene que ser un objeto con "version" y "entries".');
        }

        if (self::VERSION !== ($file['version'] ?? null)) {
            throw new FaqError(sprintf('El fichero es de otra versión ("version": %s); este portal solo entiende la %d.', json_encode($file['version'] ?? null), self::VERSION));
        }

        if (!is_array($file['entries'] ?? null) || !array_is_list($file['entries'])) {
            throw new FaqError('Falta la lista de preguntas ("entries").');
        }

        $entries = [];
        foreach ($file['entries'] as $index => $entry) {
            $entries[] = self::entry($entry, $index + 1);
        }

        return new self(
            $entries,
            self::optionalString($file, 'notebookUrl', 'El enlace del cuaderno'),
            self::optionalString($file, 'contactEmail', 'El correo de contacto'),
            self::optionalString($file, 'updatedAt', 'La fecha de revisión'),
        );
    }

    /** @return list<FaqEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getNotebookUrl(): ?string
    {
        return $this->notebookUrl;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @throws FaqError
     */
    private static function entry(mixed $entry, int $position): FaqEntry
    {
        if (!is_array($entry) || array_is_list($entry)) {
            throw new FaqError(sprintf('La pregunta número %d no es un objeto.', $position));
        }

        // Para los mensajes: el identificador si lo tiene, si no, su posición.
        $which = is_string($entry['id'] ?? null) && '' !== trim($entry['id'])
            ? sprintf('"%s"', trim($entry['id']))
            : sprintf('La pregunta número %d', $position);

        foreach (['id', 'application', 'question', 'answer'] as $field) {
            if (!is_string($entry[$field] ?? null)) {
                throw new FaqError(sprintf('%s: falta "%s" o no es texto.', $which, $field));
            }
        }

        foreach (['roles', 'keywords'] as $field) {
            $list = $entry[$field] ?? [];
            if (!is_array($list) || !array_is_list($list) || [] !== array_filter($list, static fn (mixed $item): bool => !is_string($item))) {
                throw new FaqError(sprintf('%s: "%s" tiene que ser una lista de textos.', $which, $field));
            }
        }

        $manual = $entry['manual'] ?? null;
        if (null !== $manual && !is_string($manual)) {
            throw new FaqError(sprintf('%s: "manual" tiene que ser texto (el capítulo, como "06").', $which));
        }

        /** @var list<string> $roles */
        $roles = $entry['roles'] ?? [];
        /** @var list<string> $keywords */
        $keywords = $entry['keywords'] ?? [];

        return FaqEntry::of($entry['id'], $entry['application'], $roles, $entry['question'], $entry['answer'], $keywords, $manual);
    }

    /**
     * @param array<mixed> $file
     *
     * @throws FaqError
     */
    private static function optionalString(array $file, string $field, string $what): ?string
    {
        $value = $file[$field] ?? null;

        if (null !== $value && !is_string($value)) {
            throw new FaqError(sprintf('%s ("%s") tiene que ser texto o null.', $what, $field));
        }

        return $value;
    }
}
