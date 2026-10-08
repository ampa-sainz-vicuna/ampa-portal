<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/**
 * La regla de la junta: presidencia, vicepresidencia, secretaría y tesorería
 * tienen como mucho un titular activo a la vez; vocales, los que hagan falta.
 *
 * Es un servicio de dominio porque necesita mirar a los demás miembros, no
 * solo al que se da de alta. La base de datos la repite con un índice único
 * parcial (migración de la junta) por si dos altas llegan a la vez.
 */
final readonly class SingleHolderRule
{
    public function __construct(private BoardMemberRepository $members)
    {
    }

    /**
     * @param BoardMemberId|null $except quien se está editando: su propio cargo no cuenta
     *
     * @throws BoardError si el cargo ya tiene otro titular activo
     */
    public function assertAvailable(BoardPosition $position, ?BoardMemberId $except = null): void
    {
        if (!$position->hasSingleHolder()) {
            return;
        }

        foreach ($this->members->findAll() as $member) {
            if ($member->isActive()
                && $member->getPosition() === $position
                && (null === $except || !$member->getId()->equals($except))) {
                throw BoardError::positionTaken($position);
            }
        }
    }
}
