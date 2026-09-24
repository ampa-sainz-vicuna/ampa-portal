<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Http\SessionPresenter;
use App\Portal\Infrastructure\Security\IdentityVerifier;
use App\Portal\Infrastructure\Security\InvalidIdentity;
use App\Portal\Infrastructure\Security\SessionCookie;
use App\Portal\Infrastructure\Security\SessionTokens;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entrar y salir de la suite.
 *
 * Entrar es canjear una identidad de Google por la cookie de sesión. Es el
 * único sitio de la suite que recibe algo de Google.
 */
final readonly class AuthController
{
    public function __construct(
        private IdentityVerifier $identityVerifier,
        private UserRepository $users,
        private SessionTokens $tokens,
        private SessionCookie $cookie,
        private SessionPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    /**
     * JSON: {"credential": "<ID token de Google>"}.
     *
     * 200 con quién es y la cookie puesta; 401 si Google no lo confirma; 403
     * si no tiene acceso a nada.
     */
    #[Route('/api/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function signIn(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $credential = $payload['credential'] ?? null;

        if (!is_string($credential) || '' === $credential) {
            return new JsonResponse(
                ['error' => 'Falta el campo "credential" con el token de Google.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $email = EmailAddress::fromString($this->identityVerifier->verify($credential)->getEmail());
        } catch (InvalidIdentity|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        // Que Google confirme quién eres no da acceso: hace falta estar dado de
        // alta, activo y con permiso en alguna aplicación. Cada aplicación lo
        // vuelve a comprobar en cada petición.
        $user = $this->users->findByEmail($email);

        if (null === $user || !$user->canSignIn()) {
            return new JsonResponse(
                ['error' => 'Esa cuenta no tiene acceso a ninguna aplicación del AMPA.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        // El token va SOLO en la cookie (HttpOnly), no en el cuerpo: así el
        // JavaScript de ninguna página llega a tenerlo.
        $response = new JsonResponse(($this->presenter)($user));
        $response->headers->setCookie($this->cookie->create(
            $this->tokens->issue($email),
            $this->tokens->getTtl(),
            $this->clock->now(),
        ));

        return $response;
    }

    /**
     * Borra la cookie. Sale de toda la suite a la vez: la sesión es una sola.
     * No hace falta haber entrado (borrar una cookie que no hay no molesta).
     */
    #[Route('/api/auth/salir', name: 'api_auth_sign_out', methods: ['POST'])]
    public function signOut(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookie->clear());

        return $response;
    }
}
