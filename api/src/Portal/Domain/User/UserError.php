<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

/**
 * Lo que no se puede hacer con una persona aunque los datos sean válidos. La
 * API lo traduce a un 409 (conflicto con lo que ya hay).
 */
final class UserError extends \DomainException
{
    public static function alreadyRegistered(EmailAddress $email): self
    {
        return new self(sprintf('"%s" ya está dado de alta.', $email->getValue()));
    }

    /**
     * Si el último administrador de la suite pudiera quitarse el permiso o
     * desactivarse, nadie podría volver a dar permisos sin tocar la base de
     * datos a mano. Se impide sobre uno mismo, que es como pasaría sin querer.
     */
    public static function cannotDemoteYourself(): self
    {
        return new self('No puedes quitarte a ti mismo la gestión de permisos ni desactivarte.');
    }
}
