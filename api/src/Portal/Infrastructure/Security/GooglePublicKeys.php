<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Las claves públicas con las que Google firma sus tokens de identidad: los de
 * las personas que entran (GoogleIdTokenVerifier) y los de las cuentas de
 * servicio de la suite (GoogleServiceAccountVerifier). Son las mismas.
 *
 * Google las rota, así que se cachean un rato en vez de pedirlas en cada
 * comprobación o de fijarlas en el código.
 */
final readonly class GooglePublicKeys
{
    private const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const string CACHE_KEY = 'google_oauth_jwks';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /** @return array<string, Key> */
    public function keySet(): array
    {
        return JWK::parseKeySet($this->raw());
    }

    /** @return array<string, mixed> */
    private function raw(): array
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
