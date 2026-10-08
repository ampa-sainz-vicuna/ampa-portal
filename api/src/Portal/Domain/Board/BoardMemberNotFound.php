<?php

declare(strict_types=1);

namespace App\Portal\Domain\Board;

/** La API lo traduce a un 404. */
final class BoardMemberNotFound extends \DomainException
{
    public static function withId(BoardMemberId $id): self
    {
        return new self(sprintf('No hay ningún cargo con el identificador "%s".', $id->toString()));
    }
}
