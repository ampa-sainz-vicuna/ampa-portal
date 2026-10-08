<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Board\BoardMember;

/**
 * Un cargo activo tal como lo ve cualquier persona con sesión: SOLO nombre,
 * apellidos, correo de contacto y cargo. Nada de DNI, dirección ni teléfono:
 * si algún día hace falta otro campo, se añade aquí a propósito, no por
 * descuido.
 */
final readonly class PublicBoardPresenter
{
    /** @return array{firstName: string, lastName: string, email: string, position: string} */
    public function __invoke(BoardMember $member): array
    {
        return [
            'firstName' => $member->getFirstName(),
            'lastName' => $member->getLastName(),
            'email' => $member->getEmail()->getValue(),
            'position' => $member->getPosition()->value,
        ];
    }
}
