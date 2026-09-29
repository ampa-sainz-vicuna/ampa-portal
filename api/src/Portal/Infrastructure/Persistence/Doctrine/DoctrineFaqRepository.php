<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine;

use App\Portal\Domain\Help\Faq;
use App\Portal\Domain\Help\FaqRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFaqRepository implements FaqRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): ?Faq
    {
        // Sin find(): el identificador (siempre 1) es cosa de Faq, no de quien la pide.
        return $this->entityManager->getRepository(Faq::class)->findOneBy([]);
    }

    /**
     * Una sola fila, así que guardar es un INSERT o un UPDATE: la ayuda se
     * sustituye entera o no cambia (flush va en una transacción).
     */
    public function save(Faq $faq): void
    {
        $this->entityManager->persist($faq);
        $this->entityManager->flush();
    }
}
