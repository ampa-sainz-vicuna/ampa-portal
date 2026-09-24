<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

interface UserRepository
{
    public function find(UserId $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    /** @return list<User> */
    public function findAll(): array;

    public function save(User $user): void;
}
