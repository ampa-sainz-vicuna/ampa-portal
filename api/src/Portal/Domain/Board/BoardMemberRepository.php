<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

interface BoardMemberRepository
{
    public function find(BoardMemberId $id): ?BoardMember;

    /** @return list<BoardMember> todos, activos y anteriores, sin orden */
    public function findAll(): array;

    /**
     * @throws BoardError si choca con el índice único de cargos con titular
     *                    único (dos peticiones a la vez que pasaron la comprobación)
     */
    public function save(BoardMember $member): void;
}
