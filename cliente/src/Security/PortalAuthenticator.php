<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Ampa\PortalCliente\Portal\Portal;
use Ampa\PortalCliente\Portal\PortalUnavailable;
use Ampa\PortalCliente\Portal\SessionRejected;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Quién hace la petición: coge el token de la cookie de la suite y se lo
 * pregunta al portal. En CADA petición, sin guardar nada: quitar un permiso o
 * desactivar a alguien en el portal surte efecto al momento.
 *
 * Respuestas (el front, `SessionGate` de @ampa/ui, actúa según cuál sea):
 *
 *   401  sin cookie, o el portal no la acepta → mandar a entrar al portal
 *        (y si la había, se borra: si no, el navegador la seguiría mandando)
 *   403  la sesión vale pero no tiene ningún rol aquí → "no tienes acceso"
 *   503  el portal no contesta → "prueba otra vez"; la cookie no se toca
 */
final class PortalAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly SuiteCookie $cookie,
        private readonly Portal $portal,
        private readonly ApplicationRoles $applicationRoles,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return null !== $this->cookie->tokenFrom($request);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $access = $this->portal->access((string) $this->cookie->tokenFrom($request));
        } catch (SessionRejected $e) {
            throw new BadCredentialsException($e->getMessage(), 0, $e);
        } catch (PortalUnavailable $e) {
            throw new PortalUnavailableException($e->getMessage(), 0, $e);
        }

        $roles = $this->applicationRoles->rolesFor($access);

        if ([] === $roles) {
            throw new NoAccessToApplication();
        }

        // El usuario sale de lo que ha dicho el portal, no de un proveedor de
        // usuarios: por eso el UserBadge lleva su propia función de carga.
        return new SelfValidatingPassport(new UserBadge(
            $access->getEmail(),
            static fn (): PortalUser => new PortalUser($access, $roles),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof NoAccessToApplication) {
            return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_FORBIDDEN);
        }

        if ($exception instanceof PortalUnavailableException) {
            return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = new JsonResponse(['error' => 'La sesión no es válida o ha caducado.'], Response::HTTP_UNAUTHORIZED);
        $response->headers->setCookie($this->cookie->clear());

        return $response;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Hay que entrar.'], Response::HTTP_UNAUTHORIZED);
    }
}
