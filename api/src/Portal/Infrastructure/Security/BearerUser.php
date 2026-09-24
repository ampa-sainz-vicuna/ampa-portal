<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Quién hace una llamada de servidor a servidor: el servidor de una aplicación
 * reenvía el token de la cookie que le ha llegado como
 * `Authorization: Bearer <token>`.
 *
 * Lo usan las rutas que llaman las aplicaciones (/api/acceso, /api/avisos),
 * que no pasan por el cortafuegos porque no traen cookie.
 */
final readonly class BearerUser
{
    public function __construct(
        private SessionTokens $tokens,
        private UserRepository $users,
    ) {
    }

    /**
     * @throws InvalidSessionToken sin token, token que no vale, o persona
     *                             desactivada (tiene que dejar de entrar ya,
     *                             aunque su token siga vigente)
     */
    public function from(Request $request): User
    {
        $header = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        $user = $this->users->findByEmail($this->tokens->emailFrom($token));

        if (null === $user || !$user->isActive()) {
            throw new InvalidSessionToken('Esa sesión ya no vale.');
        }

        return $user;
    }
}
