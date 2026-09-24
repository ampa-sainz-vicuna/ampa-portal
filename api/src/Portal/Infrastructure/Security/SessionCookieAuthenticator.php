<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Quién hace la petición, a partir de la cookie de sesión.
 *
 * Comprueba la firma y la caducidad del token; el usuario lo carga
 * PortalUserProvider, que mira la base de datos en cada petición.
 *
 * Sin cookie, no hace nada (supports() dice que no) y es access_control quien
 * responde 401 a través de start(). Con una cookie que no vale, responde 401 y
 * además la borra: si no, el navegador la seguiría mandando en cada petición.
 */
final class SessionCookieAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly SessionCookie $cookie,
        private readonly SessionTokens $tokens,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return null !== $this->cookie->tokenFrom($request);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $email = $this->tokens->emailFrom((string) $this->cookie->tokenFrom($request));
        } catch (InvalidSessionToken $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage(), [], 0, $e);
        }

        return new SelfValidatingPassport(new UserBadge($email->getValue()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $response = new JsonResponse(['error' => 'La sesión no es válida o ha caducado.'], Response::HTTP_UNAUTHORIZED);
        $response->headers->setCookie($this->cookie->clear());

        return $response;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Hay que entrar.'], Response::HTTP_UNAUTHORIZED);
    }
}
