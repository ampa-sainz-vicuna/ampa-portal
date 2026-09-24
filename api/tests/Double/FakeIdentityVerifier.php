<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Infrastructure\Security\IdentityVerifier;
use App\Portal\Infrastructure\Security\InvalidIdentity;
use App\Portal\Infrastructure\Security\VerifiedIdentity;

/**
 * Google, en los tests: el token ES el correo. "no-vale" hace de token que
 * Google rechaza.
 */
final class FakeIdentityVerifier implements IdentityVerifier
{
    public const string INVALID = 'no-vale';

    public function verify(string $token): VerifiedIdentity
    {
        if (self::INVALID === $token) {
            throw InvalidIdentity::malformed();
        }

        return new VerifiedIdentity($token);
    }
}
