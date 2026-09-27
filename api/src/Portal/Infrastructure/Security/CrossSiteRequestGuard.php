<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Segunda barrera contra CSRF, además de `SameSite=Lax` en la cookie.
 *
 * Con la sesión en una cookie, el navegador la manda sola; el riesgo es que
 * OTRA web haga que el navegador de alguien que ha entrado mande un POST aquí.
 * Todas las llamadas legítimas a /api salen de la propia página (mismo
 * origen), y los navegadores lo dicen en la cabecera `Sec-Fetch-Site`, que la
 * página no puede falsificar. Así que cualquier petición que cambie algo y
 * diga venir de otro sitio se rechaza.
 *
 * Se rechaza también "same-site": otro subdominio del AMPA es otro origen, y
 * ninguno necesita hacer POST aquí desde el navegador. (El cliente de las
 * aplicaciones llama al portal desde el servidor, sin esa cabecera.)
 *
 * Sin cabecera se deja pasar: no es un navegador (curl, un test, el servidor
 * de una aplicación), o es uno tan viejo que no la manda; ahí queda SameSite.
 *
 * La única excepción es la vuelta de Google al entrar (AuthController::
 * signInReturn): ese POST lo manda por definición otra web, accounts.google.com.
 * No cambia nada con la sesión de nadie salvo abrir una nueva, y lleva su
 * propia barrera contra CSRF (la cookie `g_csrf_token` de Google).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final class CrossSiteRequestGuard
{
    private const string GOOGLE_RETURN = '/api/auth/google/vuelta';

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->isMethodSafe() || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (self::GOOGLE_RETURN === $request->getPathInfo()) {
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
