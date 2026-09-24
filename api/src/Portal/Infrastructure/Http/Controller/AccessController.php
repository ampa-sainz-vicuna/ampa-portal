<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Infrastructure\Security\BearerUser;
use App\Portal\Infrastructure\Security\InvalidSessionToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La pregunta que hace cada aplicación en cada petición: "¿quién es el dueño
 * de este token y qué roles tiene en mí?".
 *
 * La llama el SERVIDOR de la aplicación (el cliente que instala, en
 * `cliente/`), no el navegador: coge el token de la cookie que le ha llegado y
 * lo manda aquí como `Authorization: Bearer`. No hace falta ninguna
 * credencial de servicio: con el token de alguien solo se pueden saber SUS
 * datos, que es justo lo que ya sabe quien tiene su token.
 *
 * Respuestas (son el contrato con el cliente; cambiarlas sube la segunda
 * cifra de la versión):
 *
 *   200 {"email", "name", "roles": [...], "notificationEmails": [...]}
 *       roles vacío = no entra en esa aplicación
 *   401 {"error"}  sin token, token que no vale, o persona desactivada
 *   404 {"error"}  la aplicación no está en el catálogo
 */
final readonly class AccessController
{
    public function __construct(
        private BearerUser $bearerUser,
        private ApplicationCatalog $catalog,
    ) {
    }

    #[Route('/api/acceso', name: 'api_access', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $application = (string) $request->query->get('aplicacion', '');

        if (null === $this->catalog->find($application)) {
            return new JsonResponse(
                ['error' => sprintf('No existe la aplicación "%s".', $application)],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $user = $this->bearerUser->from($request);
        } catch (InvalidSessionToken $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'email' => $user->getEmail()->getValue(),
            'name' => $user->getName(),
            'roles' => $user->rolesIn($application),
            'notificationEmails' => array_map(
                static fn (EmailAddress $email): string => $email->getValue(),
                $user->getNotificationEmails(),
            ),
        ]);
    }
}
