<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Portal\Infrastructure\Security\InvalidIdentity;
use App\Portal\Infrastructure\Security\ServiceAccountVerifier;

/**
 * La cuenta de servicio de la suite, en los tests: el token vale si es
 * "cuenta-de-servicio:" seguido de la cuenta admitida.
 */
final class FakeServiceAccountVerifier implements ServiceAccountVerifier
{
    public const string ACCOUNT = '123-compute@developer.gserviceaccount.com';
    public const string TOKEN = 'cuenta-de-servicio:'.self::ACCOUNT;

    public function verify(string $token): string
    {
        if (self::TOKEN !== $token) {
            throw InvalidIdentity::malformed();
        }

        return self::ACCOUNT;
    }
}
