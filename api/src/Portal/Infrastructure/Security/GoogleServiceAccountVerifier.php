<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Firebase\JWT\JWT;

/**
 * El token de identidad de Google de una cuenta de servicio de la suite.
 *
 * Lo pide el servidor de una aplicación al servidor de metadatos de Cloud Run
 * con la AUDIENCIA del portal (su dirección fija de Cloud Run, la misma que
 * las aplicaciones tienen en PORTAL_URL) y Google lo firma con las mismas
 * claves que los de las personas. Aquí se comprueba:
 *
 * - que lo firmó Google (y no ha caducado: dura una hora);
 * - que es para el portal: sin esto, un token que Google emitió para OTRO
 *   servicio serviría aquí;
 * - que la cuenta es una de las de la suite (SUITE_SERVICE_ACCOUNTS). Hoy, la
 *   de Compute Engine por defecto del proyecto, con la que corren todas.
 *
 * Sin audiencia configurada (desarrollo) no acepta ninguno: en local no hay
 * servidor de metadatos que los emita.
 */
final readonly class GoogleServiceAccountVerifier implements ServiceAccountVerifier
{
    /** @var list<string> */
    private array $accounts;

    /**
     * @param string $audience la dirección fija del portal en Cloud Run; vacía, ninguno vale
     * @param string $accounts los correos de las cuentas de servicio admitidas, separados por comas
     */
    public function __construct(
        private GooglePublicKeys $keys,
        private string $audience,
        string $accounts,
    ) {
        $this->accounts = array_values(array_filter(array_map(
            static fn (string $account): string => mb_strtolower(trim($account)),
            explode(',', $accounts),
        )));
    }

    public function verify(string $token): string
    {
        if ('' === $this->audience || [] === $this->accounts) {
            throw InvalidIdentity::notForThisApplication();
        }

        try {
            $claims = (array) JWT::decode($token, $this->keys->keySet());
        } catch (\Throwable $e) {
            throw InvalidIdentity::malformed($e);
        }

        $issuer = (string) ($claims['iss'] ?? '');

        if (!in_array($issuer, GoogleIdTokenVerifier::VALID_ISSUERS, true)) {
            throw InvalidIdentity::untrustedIssuer($issuer);
        }

        if (($claims['aud'] ?? null) !== $this->audience) {
            throw InvalidIdentity::notForThisApplication();
        }

        if (true !== ($claims['email_verified'] ?? false)) {
            throw InvalidIdentity::emailNotVerified();
        }

        $email = mb_strtolower((string) ($claims['email'] ?? ''));

        if (!in_array($email, $this->accounts, true)) {
            throw InvalidIdentity::notASuiteAccount($email);
        }

        return $email;
    }
}
