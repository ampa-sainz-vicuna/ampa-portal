<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use App\Portal\Domain\User\EmailAddress;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Clock\ClockInterface;

/**
 * El token de sesión de la suite: un JWT firmado con HS256 que solo lleva el
 * correo y la caducidad.
 *
 * HS256 (una clave que es una cadena) y no RS256 porque aquí quien firma y
 * quien comprueba es el mismo: el portal. Las aplicaciones no comprueban la
 * firma, le preguntan al portal (GET /api/acceso). Así la clave está en UN
 * secreto de toda la suite y ninguna aplicación la necesita.
 *
 * Los permisos NO van dentro: se consultan en cada petición. El token solo
 * dice "Google confirmó que esta persona es tal correo, hace menos de X días".
 */
final readonly class SessionTokens
{
    private const string ALGORITHM = 'HS256';

    /**
     * @param string $key la clave de firma (JWT_KEY); HS256 exige al menos 32
     *                    bytes y firebase/php-jwt lo comprueba
     * @param int    $ttl segundos que dura la sesión
     */
    public function __construct(
        private string $key,
        private int $ttl,
        private ClockInterface $clock,
    ) {
    }

    public function issue(EmailAddress $email): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode(['sub' => $email->getValue(), 'iat' => $now, 'exp' => $now + $this->ttl], $this->key, self::ALGORITHM);
    }

    /**
     * @throws InvalidSessionToken si la firma no vale, ha caducado o no trae correo
     */
    public function emailFrom(string $token): EmailAddress
    {
        try {
            // Key fija el algoritmo: un token que diga "alg: none" u otro
            // distinto se rechaza, aunque lo pida su cabecera.
            $claims = (array) JWT::decode($token, new Key($this->key, self::ALGORITHM));

            return EmailAddress::fromString((string) ($claims['sub'] ?? ''));
        } catch (\Throwable $e) {
            throw new InvalidSessionToken('La sesión no es válida o ha caducado.', 0, $e);
        }
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
