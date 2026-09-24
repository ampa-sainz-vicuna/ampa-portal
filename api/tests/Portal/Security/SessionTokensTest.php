<?php

declare(strict_types=1);

namespace App\Tests\Portal\Security;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Infrastructure\Security\InvalidSessionToken;
use App\Portal\Infrastructure\Security\SessionTokens;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SessionTokensTest extends TestCase
{
    private const string KEY = 'clave-de-prueba-de-al-menos-32-bytes!!';

    #[Test]
    public function el_token_lleva_el_correo_y_se_lee_de_vuelta(): void
    {
        $tokens = $this->tokens();

        $email = $tokens->emailFrom($tokens->issue(EmailAddress::fromString('admin@ampasainzvicuna.com')));

        self::assertSame('admin@ampasainzvicuna.com', $email->getValue());
    }

    #[Test]
    public function un_token_caducado_no_vale(): void
    {
        // Emitido hace ocho días con una sesión de siete.
        $old = new SessionTokens(self::KEY, 7 * 86400, new MockClock('-8 days'));
        $token = $old->issue(EmailAddress::fromString('admin@ampasainzvicuna.com'));

        $this->expectException(InvalidSessionToken::class);

        $this->tokens()->emailFrom($token);
    }

    #[Test]
    public function un_token_firmado_con_otra_clave_no_vale(): void
    {
        $other = new SessionTokens('otra-clave-distinta-de-al-menos-32-bytes', 3600, new MockClock());
        $token = $other->issue(EmailAddress::fromString('admin@ampasainzvicuna.com'));

        $this->expectException(InvalidSessionToken::class);

        $this->tokens()->emailFrom($token);
    }

    #[Test]
    public function un_token_sin_firma_no_vale_aunque_lo_pida_su_cabecera(): void
    {
        // El ataque clásico: cabecera "alg: none" y sin firma.
        $header = JWT::urlsafeB64Encode((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $claims = JWT::urlsafeB64Encode((string) json_encode(['sub' => 'admin@ampasainzvicuna.com', 'exp' => time() + 3600]));

        $this->expectException(InvalidSessionToken::class);

        $this->tokens()->emailFrom($header.'.'.$claims.'.');
    }

    #[Test]
    public function la_basura_no_vale(): void
    {
        $this->expectException(InvalidSessionToken::class);

        $this->tokens()->emailFrom('esto-no-es-un-token');
    }

    private function tokens(): SessionTokens
    {
        return new SessionTokens(self::KEY, 7 * 86400, new MockClock());
    }
}
