<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberNotFound;
use App\Portal\Domain\Board\BoardMemberRepository;

/** Da de baja a alguien de su cargo: pone la fecha de fin y queda como historia. */
final readonly class EndBoardTerm
{
    public function __construct(private BoardMemberRepository $members)
    {
    }

    /**
     * @throws BoardMemberNotFound
     * @throws BoardError                si ya estaba dada de baja
     * @throws \InvalidArgumentException si la fecha es anterior al inicio o posterior a hoy
     */
    public function __invoke(BoardMemberId $id, \DateTimeImmutable $endDate): BoardMember
    {
        $member = $this->members->find($id) ?? throw BoardMemberNotFound::withId($id);

        // Hoy en Madrid (Cloud Run va en UTC): una baja es algo que ya ha pasado.
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'));
        if ($endDate->format('Y-m-d') > $today->format('Y-m-d')) {
            throw new \InvalidArgumentException('La fecha de fin no puede ser posterior a hoy.');
        }

        $member->end($endDate);
        $this->members->save($member);

        return $member;
    }
}
