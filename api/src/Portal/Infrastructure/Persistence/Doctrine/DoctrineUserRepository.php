<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Persistence\Doctrine;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email, 'email_address')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(): array
    {
        /** @var list<User> $users */
        $users = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->getQuery()
            ->getResult();

        // Por nombre, como en una agenda. En PHP y no con ORDER BY: PostgreSQL
        // ordena según la collation con la que se creó la base, y con C.UTF-8
        // "Álvaro" sale detrás de "Zoe". Son decenas de filas.
        $collator = new \Collator('es_ES');
        usort($users, static fn (User $a, User $b): int => (int) $collator->compare($a->getName(), $b->getName()));

        return $users;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
