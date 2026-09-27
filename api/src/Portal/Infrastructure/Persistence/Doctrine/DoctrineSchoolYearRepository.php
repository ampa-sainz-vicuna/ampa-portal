<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine;

use App\Portal\Domain\Calendar\SchoolYear;
use App\Portal\Domain\Calendar\SchoolYearRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSchoolYearRepository implements SchoolYearRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(string $code): ?SchoolYear
    {
        return $this->entityManager->find(SchoolYear::class, $code);
    }

    public function findAll(): array
    {
        /** @var list<SchoolYear> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(SchoolYear::class, 's')
            ->orderBy('s.code', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(SchoolYear $schoolYear): void
    {
        $this->entityManager->persist($schoolYear);
        $this->entityManager->flush();
    }
}
