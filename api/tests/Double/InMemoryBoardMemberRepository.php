<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberRepository;

/** La junta en memoria, para probar las reglas sin base de datos. */
final class InMemoryBoardMemberRepository implements BoardMemberRepository
{
    /** @var array<string, BoardMember> */
    private array $members = [];

    public function find(BoardMemberId $id): ?BoardMember
    {
        return $this->members[$id->toString()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->members);
    }

    public function save(BoardMember $member): void
    {
        $this->members[$member->getId()->toString()] = $member;
    }
}
