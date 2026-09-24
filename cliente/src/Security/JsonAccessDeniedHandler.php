<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Ha entrado pero no puede hacer eso (por ejemplo, la pantalla de
 * administración sin ser administrador): un 403 con `{"error"}`, que es lo
 * que sabe enseñar el front (`messageOf` de @ampa/ui), en vez de la página de
 * error de Symfony. Se pone en el cortafuegos (`access_denied_handler`).
 */
final class JsonAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return new JsonResponse(['error' => 'No tienes permiso para esto.'], Response::HTTP_FORBIDDEN);
    }
}
