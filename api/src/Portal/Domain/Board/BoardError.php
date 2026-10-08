<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/**
 * Lo que no se puede hacer con la junta aunque los datos sean válidos. La API
 * lo traduce a un 409. Los mensajes no llevan datos personales.
 */
final class BoardError extends \DomainException
{
    public static function positionTaken(BoardPosition $position): self
    {
        return new self(sprintf('Ese cargo (%s) ya tiene un titular; dale de baja antes.', $position->value));
    }

    public static function alreadyEnded(): self
    {
        return new self('Esa persona ya está dada de baja en el cargo.');
    }
}
