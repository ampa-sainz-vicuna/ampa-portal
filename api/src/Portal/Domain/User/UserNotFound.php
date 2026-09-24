<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

/** La API lo traduce a un 404. */
final class UserNotFound extends \DomainException
{
    public static function withId(UserId $id): self
    {
        return new self(sprintf('No hay nadie con el identificador "%s".', $id->toString()));
    }
}
