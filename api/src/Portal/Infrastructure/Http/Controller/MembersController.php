<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Security\BearerCaller;
use App\Portal\Infrastructure\Security\InvalidSessionToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Quién hay en una aplicación: las personas activas con algún rol en ella,
 * con el correo de su CUENTA (el que las identifica en toda la suite) y su
 * nombre. Por ejemplo, tareas lo usa para elegir a quién se asigna una tarea.
 *
 * No es /api/avisos: aquel da los correos a los que avisar a quienes tienen un
 * ROL, sin decir de quién es cada uno; este da la identidad, que es lo que una
 * aplicación guarda, y además (desde el cliente 0.1.2) a dónde quiere los
 * avisos cada persona, para avisar a alguien concreto: "te han asignado una
 * tarea" va al responsable, no a todos los miembros.
 *
 * Como /api/avisos, la llama el servidor de la aplicación con el token de quien
 * hace la petición, y solo contesta si ESA persona tiene algún rol en ella.
 * Desde el cliente 0.1.3 contesta también al servidor de una aplicación sin
 * nadie detrás, con el token de la cuenta de servicio de la suite (el resumen
 * diario de tareas, a las 5:00: ver BearerCaller).
 *
 *   GET /api/personas?aplicacion=tareas
 *   200 [{"name", "email", "roles": [...], "notificationEmails": [...]}]   por nombre; solo personas activas
 *   401 sin sesión válida · 403 sin rol en esa aplicación · 404 aplicación que no existe
 */
final readonly class MembersController
{
    public function __construct(
        private BearerCaller $bearerCaller,
        private UserRepository $users,
        private ApplicationCatalog $catalog,
    ) {
    }

    #[Route('/api/personas', name: 'api_members', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $code = (string) $request->query->get('aplicacion', '');

        if (null === $this->catalog->find($code)) {
            return new JsonResponse(['error' => sprintf('No existe la aplicación "%s".', $code)], Response::HTTP_NOT_FOUND);
        }

        try {
            $caller = $this->bearerCaller->from($request);
        } catch (InvalidSessionToken $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        if (!$caller->mayAskAbout($code)) {
            return new JsonResponse(['error' => 'No tienes acceso a esa aplicación.'], Response::HTTP_FORBIDDEN);
        }

        // rolesIn() ya es vacío para las personas desactivadas.
        $members = array_filter($this->users->findAll(), static fn (User $user): bool => [] !== $user->rolesIn($code));

        return new JsonResponse(array_values(array_map(
            static fn (User $user): array => [
                'name' => $user->getName(),
                'email' => $user->getEmail()->getValue(),
                'roles' => $user->rolesIn($code),
                'notificationEmails' => array_map(
                    static fn (EmailAddress $email): string => $email->getValue(),
                    $user->getNotificationEmails(),
                ),
            ],
            $members,
        )));
    }
}
