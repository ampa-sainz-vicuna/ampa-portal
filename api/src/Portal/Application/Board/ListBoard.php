<?php

declare(strict_types=1);

namespace App\Portal\Application\Board;

use App\Portal\Domain\Board\BoardMember;
use App\Portal\Domain\Board\BoardMemberRepository;

/** La junta: quién está hoy en cada cargo y quién estuvo antes. */
final readonly class ListBoard
{
    public function __construct(private BoardMemberRepository $members)
    {
    }

    /**
     * Los activos, por cargo (presidencia primero, vocales al final) y, dentro
     * del mismo cargo, por apellidos.
     *
     * @return list<BoardMember>
     */
    public function active(): array
    {
        $active = array_values(array_filter($this->members->findAll(), static fn (BoardMember $m): bool => $m->isActive()));
        $collator = new \Collator('es_ES');
        usort($active, static fn (BoardMember $a, BoardMember $b): int => $a->getPosition()->rank() <=> $b->getPosition()->rank()
            ?:(int) $collator->compare($a->getLastName().' '.$a->getFirstName(), $b->getLastName().' '.$b->getFirstName()));

        return $active;
    }

    /**
     * La historia: los que ya dejaron el cargo, el último en irse primero.
     *
     * @return list<BoardMember>
     */
    public function past(): array
    {
        $past = array_values(array_filter($this->members->findAll(), static fn (BoardMember $m): bool => !$m->isActive()));
        usort($past, static fn (BoardMember $a, BoardMember $b): int => $b->getEndDate() <=> $a->getEndDate()
            ?: $a->getPosition()->rank() <=> $b->getPosition()->rank());

        return $past;
    }
}
