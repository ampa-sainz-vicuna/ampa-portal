<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Quién llama a /api/avisos o /api/personas: primero se prueba como sesión de
 * una persona (lo de siempre, BearerUser); si no lo es, como token de la
 * cuenta de servicio de la suite (desde el cliente 0.1.3).
 *
 * /api/acceso NO lo usa: "quién es y qué puede hacer" solo tiene sentido con
 * una persona.
 */
final readonly class BearerCaller
{
    public function __construct(
        private BearerUser $bearerUser,
        private ServiceAccountVerifier $serviceAccounts,
    ) {
    }

    /**
     * @throws InvalidSessionToken si no es ni una sesión válida ni una cuenta de la suite
     */
    public function from(Request $request): Caller
    {
        try {
            return Caller::person($this->bearerUser->from($request));
        } catch (InvalidSessionToken $notAPerson) {
            $header = (string) $request->headers->get('Authorization', '');
            $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

            if ('' === $token) {
                throw $notAPerson;
            }

            try {
                return Caller::application($this->serviceAccounts->verify($token));
            } catch (InvalidIdentity) {
                throw $notAPerson;
            }
        }
    }
}
