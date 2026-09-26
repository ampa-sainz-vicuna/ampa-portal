<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

/**
 * El token de Google no vale. Los motivos se distinguen aquí para el registro,
 * pero hacia fuera todos acaban en un 401.
 */
final class InvalidIdentity extends \RuntimeException
{
    public static function malformed(?\Throwable $previous = null): self
    {
        return new self('El token de identidad no es válido o ha caducado.', 0, $previous);
    }

    public static function notForThisApplication(): self
    {
        return new self('El token de identidad no fue emitido para esta aplicación.');
    }

    public static function untrustedIssuer(string $issuer): self
    {
        return new self(sprintf('Emisor no reconocido: "%s".', $issuer));
    }

    public static function emailNotVerified(): self
    {
        return new self('Google no ha verificado esa dirección de correo.');
    }

    public static function outsideOrganisation(): self
    {
        return new self('Esa cuenta no pertenece a la organización.');
    }

    public static function notASuiteAccount(string $account): self
    {
        return new self(sprintf('La cuenta de servicio "%s" no es de la suite.', $account));
    }
}
