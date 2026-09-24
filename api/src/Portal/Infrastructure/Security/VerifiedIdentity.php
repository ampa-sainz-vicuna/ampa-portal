<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

/**
 * Quién dice Google que eres: el correo verificado.
 *
 * Solo el correo. El nombre que se enseña en la suite es el que puso quien dio
 * el alta (User), no el del perfil de Google.
 */
final readonly class VerifiedIdentity
{
    public function __construct(private string $email)
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
