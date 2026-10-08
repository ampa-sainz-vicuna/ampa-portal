<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberRepository;
use App\Portal\Domain\Board\SingleHolderRule;

/** Da de alta a una persona en un cargo de la junta. */
final readonly class AppointBoardMember
{
    public function __construct(
        private BoardMemberRepository $members,
        private SingleHolderRule $singleHolder,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si algún texto no vale
     * @throws BoardError                si el cargo es de titular único y ya lo tiene otra persona
     */
    public function __invoke(BoardMemberData $data): BoardMember
    {
        $this->singleHolder->assertAvailable($data->getPosition());

        $member = BoardMember::appoint(
            BoardMemberId::generate(),
            $data->getFirstName(),
            $data->getLastName(),
            $data->getDocument(),
            $data->getAddress(),
            $data->getEmail(),
            $data->getPhone(),
            $data->getPosition(),
            $data->getStartDate(),
        );
        $this->members->save($member);

        return $member;
    }
}
