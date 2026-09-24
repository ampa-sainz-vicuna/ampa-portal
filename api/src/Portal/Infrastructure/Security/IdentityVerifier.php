<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

/**
 * Comprueba una identidad emitida por Google y devuelve quién es.
 *
 * Es una interfaz y no una clase suelta para que los tests puedan sustituirla:
 * no hay forma de fabricar un token real firmado por Google.
 */
interface IdentityVerifier
{
    /**
     * @throws InvalidIdentity si el token no vale, ha caducado, no es para esta
     *                         aplicación o el correo no está verificado
     */
    public function verify(string $token): VerifiedIdentity;
}
