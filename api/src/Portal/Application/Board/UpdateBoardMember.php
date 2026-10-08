<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberNotFound;
use App\Portal\Domain\Board\BoardMemberRepository;
use App\Portal\Domain\Board\SingleHolderRule;

/** Edita la ficha de un cargo (activo o anterior). */
final readonly class UpdateBoardMember
{
    public function __construct(
        private BoardMemberRepository $members,
        private SingleHolderRule $singleHolder,
    ) {
    }

    /**
     * @throws BoardMemberNotFound
     * @throws \InvalidArgumentException si algún dato no vale
     * @throws BoardError                si cambia a un cargo de titular único que ya tiene otra persona
     */
    public function __invoke(BoardMemberId $id, BoardMemberData $data): BoardMember
    {
        $member = $this->members->find($id) ?? throw BoardMemberNotFound::withId($id);

        // Un cargo anterior ya no ocupa su puesto: la regla solo cuenta con los activos.
        if ($member->isActive()) {
            $this->singleHolder->assertAvailable($data->getPosition(), $id);
        }

        $member->update(
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
