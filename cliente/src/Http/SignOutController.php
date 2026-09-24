<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Http;

use Ampa\PortalCliente\Security\SuiteCookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/auth/salir`: borra la cookie de la suite. Se sale de TODAS las
 * aplicaciones a la vez, porque la sesión es una sola.
 *
 * Está en cada aplicación, y no solo en el portal, para que el botón de salir
 * sea una llamada a la propia API (mismo origen, sin CORS). No hace falta
 * haber entrado: borrar una cookie que no hay no molesta.
 */
final readonly class SignOutController
{
    public function __construct(private SuiteCookie $cookie)
    {
    }

    public function __invoke(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookie->clear());

        return $response;
    }
}
