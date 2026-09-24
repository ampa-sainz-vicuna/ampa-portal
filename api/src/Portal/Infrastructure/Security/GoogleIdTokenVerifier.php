<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
    private const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const string CACHE_KEY = 'google_oauth_jwks';

    /** Google emite con cualquiera de estos dos valores de "iss". */
    private const array VALID_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @param string $hostedDomain dominio de Workspace exigido; vacío para
     *                             admitir cualquier cuenta de Google (tareas
     *                             admitirá cuentas personales: entonces la
     *                             barrera son solo los permisos)
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $clientId,
        private string $hostedDomain,
    ) {
    }

    public function verify(string $token): VerifiedIdentity
    {
        try {
            $claims = (array) JWT::decode($token, JWK::parseKeySet($this->publicKeys()));
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

    /**
     * Las claves públicas de Google rotan, así que se cachean un rato en vez de
     * pedirlas en cada inicio de sesión o de fijarlas en el código.
     *
     * @return array<string, mixed>
     */
    private function publicKeys(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            /** @var array<string, mixed> */
            return $item->get();
        }

        $keys = $this->httpClient->request('GET', self::JWKS_URL)->toArray();

        $item->set($keys);
        $item->expiresAfter(3600);
        $this->cache->save($item);

        return $keys;
    }
}
