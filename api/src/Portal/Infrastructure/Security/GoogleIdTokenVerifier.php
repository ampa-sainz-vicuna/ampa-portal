<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Firebase\JWT\JWT;

/**
 * Verifica un ID token de Google contra las claves públicas de Google.
 *
 * Viene de listados (que a su vez lo copió de fichajes), y desde el portal es
 * el ÚNICO sitio de la suite que habla con Google: el navegador obtiene el
 * token con Google Identity Services, el portal comprueba que lo firmó Google
 * y emite el token propio de la suite. No se guarda nada de Google más allá
 * del correo.
 */
final readonly class GoogleIdTokenVerifier implements IdentityVerifier
{
    /** Google emite con cualquiera de estos dos valores de "iss". */
    public const array VALID_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @param string $hostedDomain dominio de Workspace exigido; vacío para
     *                             admitir cualquier cuenta de Google (tareas
     *                             admitirá cuentas personales: entonces la
     *                             barrera son solo los permisos)
     */
    public function __construct(
        private GooglePublicKeys $keys,
        private string $clientId,
        private string $hostedDomain,
    ) {
    }

    public function verify(string $token): VerifiedIdentity
    {
        try {
            $claims = (array) JWT::decode($token, $this->keys->keySet());
        } catch (\Throwable $e) {
            // Firma inválida, token caducado o mal formado. No se distingue el
            // motivo hacia fuera.
            throw InvalidIdentity::malformed($e);
        }

        $issuer = (string) ($claims['iss'] ?? '');

        if (!in_array($issuer, self::VALID_ISSUERS, true)) {
            throw InvalidIdentity::untrustedIssuer($issuer);
        }

        // Sin esto, un token legítimo emitido para OTRA aplicación de Google
        // serviría para entrar aquí. Es el fallo clásico de esta integración.
        if (($claims['aud'] ?? null) !== $this->clientId) {
            throw InvalidIdentity::notForThisApplication();
        }

        if (true !== ($claims['email_verified'] ?? false)) {
            throw InvalidIdentity::emailNotVerified();
        }

        if ('' !== $this->hostedDomain && ($claims['hd'] ?? null) !== $this->hostedDomain) {
            throw InvalidIdentity::outsideOrganisation();
        }

        $email = $claims['email'] ?? null;

        if (!is_string($email) || '' === $email) {
            throw InvalidIdentity::malformed();
        }

        return new VerifiedIdentity($email);
    }
}
