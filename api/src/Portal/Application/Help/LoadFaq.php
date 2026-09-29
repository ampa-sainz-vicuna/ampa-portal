<?php

declare(strict_types=1);

namespace App\Portal\Application\Help;

use App\Portal\Domain\Help\Faq;
use App\Portal\Domain\Help\FaqError;
use App\Portal\Domain\Help\FaqRepository;
use App\Portal\Domain\Suite\ApplicationCatalog;
use Psr\Clock\ClockInterface;

/**
 * Carga el fichero de preguntas: sustituye la ayuda entera por la del
 * fichero, o no cambia nada si algo no vale (se comprueba todo antes de
 * guardar, y guardar es una sola fila).
 *
 * Lo usan la pantalla *Ayuda* (PUT /api/admin/ayuda) y el comando
 * app:ayuda:cargar.
 */
final readonly class LoadFaq
{
    public function __construct(
        private FaqRepository $faqs,
        private ApplicationCatalog $catalog,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws FaqError
     */
    public function __invoke(FaqFile $file): Faq
    {
        $faq = $this->faqs->current();
        $now = $this->clock->now();

        if (null === $faq) {
            $faq = Faq::publish($file->getEntries(), $file->getNotebookUrl(), $file->getContactEmail(), $file->getUpdatedAt(), $this->catalog, $now);
        } else {
            $faq->replace($file->getEntries(), $file->getNotebookUrl(), $file->getContactEmail(), $file->getUpdatedAt(), $this->catalog, $now);
        }

        $this->faqs->save($faq);

        return $faq;
    }
}
