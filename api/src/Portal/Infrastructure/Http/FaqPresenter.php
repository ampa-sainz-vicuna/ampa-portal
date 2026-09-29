<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Help\Faq;
use App\Portal\Domain\Help\FaqEntry;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\Grants;

/**
 * La ayuda, en las dos formas en que sale del portal: lo que ve cada persona
 * (GET /api/ayuda, contrato con @ampa/ui) y el resumen de la pantalla *Ayuda*
 * (GET y PUT /api/admin/ayuda).
 */
final readonly class FaqPresenter
{
    public function __construct(private ApplicationCatalog $catalog)
    {
    }

    /**
     * Sin los roles de cada pregunta: ya están aplicados, y a quien la lee no
     * le dicen nada.
     *
     * @return array{
     *     notebookUrl: string|null,
     *     contactEmail: string|null,
     *     updatedAt: string|null,
     *     entries: list<array{id: string, application: string, question: string, answer: string, keywords: list<string>, manual: string|null}>
     * }
     */
    public function forReader(?Faq $faq, Grants $grants): array
    {
        return [
            'notebookUrl' => $faq?->getNotebookUrl(),
            'contactEmail' => $faq?->getContactEmail(),
            'updatedAt' => $faq?->getUpdatedAt(),
            'entries' => array_map(
                static fn (FaqEntry $entry): array => [
                    'id' => $entry->getId(),
                    'application' => $entry->getApplication(),
                    'question' => $entry->getQuestion(),
                    'answer' => $entry->getAnswer(),
                    'keywords' => $entry->getKeywords(),
                    'manual' => $entry->getManual(),
                ],
                $faq?->entriesVisibleWith($grants) ?? [],
            ),
        ];
    }

    /**
     * Cuántas preguntas hay de cada aplicación, TODAS las de la suite aunque
     * no tengan ninguna: un cero en facturación dice que falta su ayuda.
     *
     * @return array{
     *     loadedAt: string|null,
     *     updatedAt: string|null,
     *     notebookUrl: string|null,
     *     contactEmail: string|null,
     *     total: int,
     *     byApplication: list<array{application: string, name: string, entries: int}>
     * }
     */
    public function forAdmin(?Faq $faq): array
    {
        $counts = $faq?->countByApplication() ?? [];

        $byApplication = [['application' => FaqEntry::GENERAL, 'name' => 'General', 'entries' => $counts[FaqEntry::GENERAL] ?? 0]];
        foreach ($this->catalog->all() as $application) {
            $byApplication[] = [
                'application' => $application->getCode(),
                'name' => $application->getName(),
                'entries' => $counts[$application->getCode()] ?? 0,
            ];
        }

        return [
            // ISO 8601; null si todavía no se ha cargado nunca.
            'loadedAt' => $faq?->getLoadedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $faq?->getUpdatedAt(),
            'notebookUrl' => $faq?->getNotebookUrl(),
            'contactEmail' => $faq?->getContactEmail(),
            'total' => array_sum($counts),
            'byApplication' => $byApplication,
        ];
    }
}
