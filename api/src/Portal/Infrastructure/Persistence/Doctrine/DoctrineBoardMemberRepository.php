<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine;

use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberRepository;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineBoardMemberRepository implements BoardMemberRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(BoardMemberId $id): ?BoardMember
    {
        return $this->entityManager->find(BoardMember::class, $id);
    }

    public function findAll(): array
    {
        /** @var list<BoardMember> */
        return $this->entityManager->getRepository(BoardMember::class)->findAll();
    }

    public function save(BoardMember $member): void
    {
        $this->entityManager->persist($member);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // El único índice único de la tabla es el del titular único por
            // cargo (uniq_board_single_holder): dos altas simultáneas pasaron
            // la comprobación del caso de uso. No se encadena la excepción:
            // su mensaje lleva los valores de la fila.
            throw BoardError::positionTaken($member->getPosition());
        } catch (DriverException) {
            // Cualquier otro fallo del driver (un CHECK, la FK…): Postgres mete
            // la fila entera (DNI, dirección, teléfono) en el DETAIL y Symfony
            // registraría la excepción. Por eso se relanza una genérica, SIN
            // encadenar la original.
            throw new \RuntimeException('No se ha podido guardar el cargo de la junta.');
        }
    }
}
