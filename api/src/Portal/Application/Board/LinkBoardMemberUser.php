<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberNotFound;
use App\Portal\Domain\Board\BoardMemberRepository;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserNotFound;
use App\Portal\Domain\User\UserRepository;

/**
 * Asocia un cargo a una persona que ya tiene el portal (o quita la
 * asociación con null). Sirve para saber, más adelante, qué usuario de la
 * suite es la secretaria.
 */
final readonly class LinkBoardMemberUser
{
    public function __construct(
        private BoardMemberRepository $members,
        private UserRepository $users,
    ) {
    }

    /**
     * @throws BoardMemberNotFound
     * @throws UserNotFound        si el usuario no existe
     */
    public function __invoke(BoardMemberId $id, ?UserId $userId): BoardMember
    {
        $member = $this->members->find($id) ?? throw BoardMemberNotFound::withId($id);

        if (null !== $userId && null === $this->users->find($userId)) {
            throw UserNotFound::withId($userId);
        }

        $member->linkUser($userId);
        $this->members->save($member);

        return $member;
    }
}
