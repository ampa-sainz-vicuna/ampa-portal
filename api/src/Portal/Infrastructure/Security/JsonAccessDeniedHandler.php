<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Ha entrado pero no puede hacer eso (la pantalla de permisos sin ser quien
 * los gestiona): un 403 con el mismo `{"error"}` que el resto de la API, que
 * es lo que sabe enseñar el front (`messageOf` de @ampa/ui), en vez de la
 * página de error de Symfony.
 */
final class JsonAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return new JsonResponse(['error' => 'No tienes permiso para esto.'], Response::HTTP_FORBIDDEN);
    }
}
