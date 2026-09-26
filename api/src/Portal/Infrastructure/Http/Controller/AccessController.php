<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\User\RecordVisit;
use App\Portal\Domain\Suite\Application;
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
 *   200 {"email", "name", "roles": [...], "notificationEmails": [...],
 *        "applications": [{"code", "name", "url"}]}
 *       roles vacío = no entra en esa aplicación; applications = a cuáles
 *       puede ir (desde el cliente 0.1.4: el selector de la barra)
 *   401 {"error"}  sin token, token que no vale, o persona desactivada
 *   404 {"error"}  la aplicación no está en el catálogo
 */
final readonly class AccessController
{
    public function __construct(
        private BearerUser $bearerUser,
        private ApplicationCatalog $catalog,
        private RecordVisit $recordVisit,
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

        // Casi nadie pasa por el portal: se entra directo en cada aplicación
        // con la cookie. Es aquí donde se sabe que alguien sigue usando la suite.
        ($this->recordVisit)($user);

        return new JsonResponse([
            'email' => $user->getEmail()->getValue(),
            'name' => $user->getName(),
            'roles' => $user->rolesIn($application),
            'notificationEmails' => array_map(
                static fn (EmailAddress $email): string => $email->getValue(),
                $user->getNotificationEmails(),
            ),
            'applications' => array_map(
                static fn (Application $reachable): array => [
                    'code' => $reachable->getCode(),
                    'name' => $reachable->getName(),
                    'url' => $reachable->getUrl(),
                ],
                $this->catalog->reachableWith($user->getGrants()),
            ),
        ]);
    }
}
