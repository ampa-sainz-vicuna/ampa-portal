<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests;

use Ampa\PortalCliente\Heartbeat\GoogleTokenInfoVerifier;
use Ampa\PortalCliente\Heartbeat\InvalidHeartbeat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * La comprobación del token del latido, con Google haciendo de tokeninfo en
 * un cliente HTTP falso: lo que contestaría para un token bueno o malo.
 */
final class GoogleTokenInfoVerifierTest extends TestCase
{
    private const string AUDIENCE = 'https://tareas.ampasainzvicuna.com';
    private const string ACCOUNT = '123-compute@developer.gserviceaccount.com';

    private ?string $askedUrl = null;

    #[Test]
    public function acepta_el_token_de_la_suite_para_esta_aplicacion(): void
    {
        $this->verifier($this->claims())->verify('token-bueno');

        self::assertSame('https://oauth2.googleapis.com/tokeninfo?id_token=token-bueno', $this->askedUrl);
    }

    #[Test]
    public function un_token_para_otra_aplicacion_no_vale(): void
    {
        $this->expectException(InvalidHeartbeat::class);
        $this->expectExceptionMessage('no es para esta aplicación');

        $this->verifier($this->claims(['aud' => 'https://listados.ampasainzvicuna.com']))->verify('token');
    }

    #[Test]
    public function una_cuenta_que_no_es_de_la_suite_no_vale(): void
    {
        $this->expectException(InvalidHeartbeat::class);

        $this->verifier($this->claims(['email' => 'alguien@gmail.com']))->verify('token');
    }

    #[Test]
    public function si_google_dice_que_no_vale_no_vale(): void
    {
        $this->expectException(InvalidHeartbeat::class);

        $this->verifier(['error' => 'invalid_token'], 400)->verify('caducado');
    }

    #[Test]
    public function sin_configurar_no_acepta_ninguno_ni_pregunta_a_google(): void
    {
        $verifier = new GoogleTokenInfoVerifier(new MockHttpClient(static fn () => throw new \LogicException('No debería preguntar.')), '', '');

        $this->expectException(InvalidHeartbeat::class);

        $verifier->verify('token-bueno');
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function claims(array $override = []): array
    {
        // Así contesta tokeninfo: el correo verificado, como texto.
        return [...[
            'iss' => 'https://accounts.google.com',
            'aud' => self::AUDIENCE,
            'email' => self::ACCOUNT,
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
        ], ...$override];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function verifier(array $body, int $status = 200): GoogleTokenInfoVerifier
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($body, $status): MockResponse {
            $this->askedUrl = $url;

            return new MockResponse((string) json_encode($body), ['http_code' => $status]);
        });

        return new GoogleTokenInfoVerifier($http, self::AUDIENCE.'/', 'otra@x.iam.gserviceaccount.com, '.strtoupper(self::ACCOUNT));
    }
}
