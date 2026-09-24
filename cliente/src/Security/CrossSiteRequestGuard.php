<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Segunda barrera contra CSRF, además de `SameSite=Lax` en la cookie. La
 * misma que en el portal.
 *
 * Con la sesión en una cookie, el navegador la manda sola; el riesgo es que
 * OTRA web haga que el navegador de alguien que ha entrado mande un POST a
 * esta aplicación. Todas las llamadas legítimas a /api salen de la propia
 * página (mismo origen), y los navegadores lo dicen en la cabecera
 * `Sec-Fetch-Site`, que la página no puede falsificar. Cualquier petición que
 * cambie algo y diga venir de otro sitio (incluido otro subdominio del AMPA)
 * se rechaza.
 *
 * Sin cabecera se deja pasar: no es un navegador (curl, un test) o es uno tan
 * viejo que no la manda; ahí queda SameSite.
 *
 * Se registra en el bundle para /api (kernel.request, prioridad alta).
 */
final class CrossSiteRequestGuard
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->isMethodSafe() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $site = $request->headers->get('Sec-Fetch-Site');

        if (null === $site || 'same-origin' === $site) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'Petición rechazada: no viene de esta misma página.'],
            Response::HTTP_FORBIDDEN,
        ));
    }
}
