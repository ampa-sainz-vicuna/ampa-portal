<?php

declare(strict_types=1);

namespace App\Portal\Domain\Help;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;

/**
 * La ayuda de toda la suite: las preguntas con sus respuestas, el enlace al
 * cuaderno de NotebookLM donde preguntar lo que no esté y a quién escribir.
 *
 * El portal la guarda y la sirve (GET /api/ayuda), y el botón de ayuda de la
 * barra de @ampa/ui la enseña con su buscador en cada aplicación: así hay una
 * sola ayuda, que se carga en un sitio. Se carga entera de una vez, desde el
 * fichero faq.json que se prepara fuera de este repositorio (es público, y la
 * ayuda cuenta cosas del AMPA); cargarla otra vez la sustituye entera.
 *
 * Hay una sola: una fila en la base, con las preguntas en JSON. Son decenas o
 * pocos cientos, se leen siempre juntas (el buscador está en el navegador) y
 * se sustituyen siempre juntas, igual que los días de un curso en SchoolYear.
 */
final class Faq
{
    /** El identificador de la única fila: la ayuda no tiene identidad propia. */
    private const int ID = 1;

    private const int MAX_ENTRIES = 2000;
    private const int MAX_URL = 500;

    /** Siempre ID: solo lo usa Doctrine, para saber qué fila es. */
    private int $id;

    /** @var list<array{id: string, application: string, roles: list<string>, question: string, answer: string, keywords: list<string>, manual: string|null}> */
    private array $entries;

    private ?string $notebookUrl;
    private ?string $contactEmail;
    private ?string $updatedAt;
    private \DateTimeImmutable $loadedAt;

    /**
     * @param list<FaqEntry> $entries
     *
     * @throws FaqError
     */
    private function __construct(
        array $entries,
        ?string $notebookUrl,
        ?string $contactEmail,
        ?string $updatedAt,
        ApplicationCatalog $catalog,
        \DateTimeImmutable $loadedAt,
    ) {
        $this->id = self::ID;
        $this->replace($entries, $notebookUrl, $contactEmail, $updatedAt, $catalog, $loadedAt);
    }

    /**
     * La primera vez que se carga.
     *
     * @param list<FaqEntry> $entries
     *
     * @throws FaqError
     */
    public static function publish(
        array $entries,
        ?string $notebookUrl,
        ?string $contactEmail,
        ?string $updatedAt,
        ApplicationCatalog $catalog,
        \DateTimeImmutable $loadedAt,
    ): self {
        return new self($entries, $notebookUrl, $contactEmail, $updatedAt, $catalog, $loadedAt);
    }

    /**
     * Sustituye la ayuda entera: lo que no venga, desaparece. Si algo no vale,
     * no cambia nada.
     *
     * @param list<FaqEntry> $entries
     *
     * @throws FaqError
     */
    public function replace(
        array $entries,
        ?string $notebookUrl,
        ?string $contactEmail,
        ?string $updatedAt,
        ApplicationCatalog $catalog,
        \DateTimeImmutable $loadedAt,
    ): void {
        $checkedEntries = self::checkedEntries($entries, $catalog);
        $checkedUrl = self::checkedNotebookUrl($notebookUrl);
        $checkedEmail = self::checkedContactEmail($contactEmail);
        $checkedDate = self::checkedUpdatedAt($updatedAt);

        $this->entries = $checkedEntries;
        $this->notebookUrl = $checkedUrl;
        $this->contactEmail = $checkedEmail;
        $this->updatedAt = $checkedDate;
        $this->loadedAt = $loadedAt;
    }

    /** @return list<FaqEntry> en el orden del fichero */
    public function getEntries(): array
    {
        return array_map(FaqEntry::fromArray(...), $this->entries);
    }

    /** @return list<FaqEntry> las que puede ver quien tiene estos permisos, en el orden del fichero */
    public function entriesVisibleWith(Grants $grants): array
    {
        return array_values(array_filter(
            $this->getEntries(),
            static fn (FaqEntry $entry): bool => $entry->isVisibleWith($grants),
        ));
    }

    /** @return array<string, int> aplicación (o "general") → cuántas preguntas tiene */
    public function countByApplication(): array
    {
        $counts = [];
        foreach ($this->entries as $entry) {
            $counts[$entry['application']] = ($counts[$entry['application']] ?? 0) + 1;
        }

        return $counts;
    }

    /** El cuaderno de NotebookLM, para lo que no esté en las preguntas. */
    public function getNotebookUrl(): ?string
    {
        return $this->notebookUrl;
    }

    /** A quién escribir si no hay respuesta. */
    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /** AAAA-MM-DD: cuándo se revisó el fichero (lo dice el propio fichero). */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /** Cuándo se cargó en el portal. */
    public function getLoadedAt(): \DateTimeImmutable
    {
        return $this->loadedAt;
    }

    /**
     * @param list<FaqEntry> $entries
     *
     * @return list<array{id: string, application: string, roles: list<string>, question: string, answer: string, keywords: list<string>, manual: string|null}>
     *
     * @throws FaqError
     */
    private static function checkedEntries(array $entries, ApplicationCatalog $catalog): array
    {
        if (count($entries) > self::MAX_ENTRIES) {
            throw new FaqError(sprintf('Demasiadas preguntas: %d (%d como mucho).', count($entries), self::MAX_ENTRIES));
        }

        $seen = [];
        foreach ($entries as $entry) {
            $id = $entry->getId();

            if (isset($seen[$id])) {
                throw new FaqError(sprintf('El identificador "%s" está repetido: cada pregunta necesita el suyo.', $id));
            }
            $seen[$id] = true;

            if (FaqEntry::GENERAL === $entry->getApplication()) {
                continue;
            }

            $application = $catalog->find($entry->getApplication());
            if (null === $application) {
                throw new FaqError(sprintf('"%s": no existe la aplicación "%s" (tiene que ser "general" o una de la suite).', $id, $entry->getApplication()));
            }

            foreach ($entry->getRoles() as $role) {
                if (!$application->hasRole($role)) {
                    throw new FaqError(sprintf('"%s": la aplicación "%s" no tiene el rol "%s".', $id, $entry->getApplication(), $role));
                }
            }
        }

        return array_map(static fn (FaqEntry $entry): array => $entry->toArray(), $entries);
    }

    /**
     * Solo https: el enlace se pinta tal cual en todas las aplicaciones, y
     * cualquier otra cosa (http, javascript:) sería un agujero.
     *
     * @throws FaqError
     */
    private static function checkedNotebookUrl(?string $url): ?string
    {
        $url = null === $url ? null : trim($url);

        if (null === $url || '' === $url) {
            return null;
        }

        if (mb_strlen($url) > self::MAX_URL
            || false === filter_var($url, \FILTER_VALIDATE_URL)
            || 'https' !== strtolower((string) parse_url($url, \PHP_URL_SCHEME))) {
            throw new FaqError(sprintf('El enlace del cuaderno ("notebookUrl") tiene que ser una dirección https: "%s" no vale.', mb_substr($url, 0, 60)));
        }

        return $url;
    }

    /**
     * @throws FaqError
     */
    private static function checkedContactEmail(?string $email): ?string
    {
        if (null === $email || '' === trim($email)) {
            return null;
        }

        try {
            return EmailAddress::fromString($email)->getValue();
        } catch (\InvalidArgumentException) {
            throw new FaqError(sprintf('El correo de contacto ("contactEmail") no vale: "%s".', mb_substr($email, 0, 60)));
        }
    }

    /**
     * @throws FaqError
     */
    private static function checkedUpdatedAt(?string $date): ?string
    {
        if (null === $date) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new FaqError(sprintf('La fecha de revisión ("updatedAt") tiene que ser AAAA-MM-DD: "%s" no vale.', mb_substr($date, 0, 30)));
        }

        return $date;
    }
}
