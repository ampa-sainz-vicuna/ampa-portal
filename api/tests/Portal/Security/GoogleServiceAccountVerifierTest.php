<?php

declare(strict_types=1);

namespace App\Tests\Portal\Security;

use App\Portal\Infrastructure\Security\GooglePublicKeys;
use App\Portal\Infrastructure\Security\GoogleServiceAccountVerifier;
use App\Portal\Infrastructure\Security\InvalidIdentity;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * La comprobación de verdad, con tokens firmados por una clave RSA de mentira
 * que hace de Google: sus claves públicas las "sirve" un cliente HTTP falso.
 */
final class GoogleServiceAccountVerifierTest extends TestCase
{
    private const string AUDIENCE = 'https://ampa-portal-123.europe-west1.run.app';
    private const string ACCOUNT = '123-compute@developer.gserviceaccount.com';
    private const string KID = 'clave-de-prueba';

    private static \OpenSSLAsymmetricKey $privateKey;

    public static function setUpBeforeClass(): void
    {
        self::$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA])
            ?: throw new \RuntimeException('No se ha podido generar la clave.');
    }

    #[Test]
    public function acepta_la_cuenta_de_la_suite_con_la_audiencia_del_portal(): void
    {
        self::assertSame(self::ACCOUNT, $this->verifier()->verify($this->token()));
    }

    #[Test]
    public function un_token_para_otro_servicio_no_vale(): void
    {
        $this->expectException(InvalidIdentity::class);

        $this->verifier()->verify($this->token(['aud' => 'https://ampa-tareas-123.europe-west1.run.app']));
    }

    #[Test]
    public function una_cuenta_que_no_es_de_la_suite_no_vale(): void
    {
        $this->expectException(InvalidIdentity::class);

        $this->verifier()->verify($this->token(['email' => 'otra@otro-proyecto.iam.gserviceaccount.com']));
    }

    #[Test]
    public function un_token_caducado_no_vale(): void
    {
        $this->expectException(InvalidIdentity::class);

        $this->verifier()->verify($this->token(['exp' => time() - 3600]));
    }

    #[Test]
    public function sin_audiencia_configurada_no_acepta_ninguno(): void
    {
        // Desarrollo: no hay servidor de metadatos que emita estos tokens.
        $this->expectException(InvalidIdentity::class);

        $this->verifier(audience: '')->verify($this->token());
    }

    #[Test]
    public function la_sesion_de_una_persona_no_es_una_cuenta_de_servicio(): void
    {
        $this->expectException(InvalidIdentity::class);

        $this->verifier()->verify(JWT::encode(['sub' => self::ACCOUNT, 'exp' => time() + 60], 'clave-de-prueba-de-al-menos-32-bytes!!', 'HS256'));
    }

    private function verifier(string $audience = self::AUDIENCE): GoogleServiceAccountVerifier
    {
        $details = openssl_pkey_get_details(self::$privateKey) ?: throw new \RuntimeException('Sin detalles de la clave.');
        $jwks = ['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::KID,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ]]];

        $keys = new GooglePublicKeys(new MockHttpClient(new MockResponse((string) json_encode($jwks))), new ArrayAdapter());

        return new GoogleServiceAccountVerifier($keys, $audience, ' OTRA@example.com , '.strtoupper(self::ACCOUNT));
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims = []): string
    {
        return JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => self::AUDIENCE,
            'email' => self::ACCOUNT,
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
            ...$claims,
        ], self::$privateKey, 'RS256', self::KID);
    }
}
