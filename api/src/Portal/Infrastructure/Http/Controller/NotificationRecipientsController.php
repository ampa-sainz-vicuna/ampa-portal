<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Security\BearerUser;
use App\Portal\Infrastructure\Security\InvalidSessionToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A quién avisar: los correos de todas las personas con un rol en una
 * aplicación, cada una en el correo que haya elegido. Por ejemplo, cuando un
 * empleado pide vacaciones, fichajes avisa a sus administradores.
 *
 * Como /api/acceso, la llama el servidor de la aplicación con el token de
 * quien está haciendo la petición. Solo contesta si ESA persona tiene algún
 * rol en la aplicación: con una sesión de listados no se pueden sacar los
 * correos de los administradores de fichajes.
 *
 *   GET /api/avisos?aplicacion=fichajes&rol=admin
 *   200 [{"name", "emails": [...]}]   por nombre; solo personas activas
 *   401 sin sesión válida · 403 sin rol en esa aplicación · 404 aplicación o rol que no existe
 */
final readonly class NotificationRecipientsController
{
    public function __construct(
        private BearerUser $bearerUser,
        private UserRepository $users,
        private ApplicationCatalog $catalog,
    ) {
    }

    #[Route('/api/avisos', name: 'api_notification_recipients', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $code = (string) $request->query->get('aplicacion', '');
        $role = (string) $request->query->get('rol', '');

        if (!($this->catalog->find($code)?->hasRole($role) ?? false)) {
            return new JsonResponse(
                ['error' => sprintf('No existe el rol "%s" en la aplicación "%s".', $role, $code)],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $caller = $this->bearerUser->from($request);
        } catch (InvalidSessionToken $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        if ([] === $caller->rolesIn($code)) {
            return new JsonResponse(['error' => 'No tienes acceso a esa aplicación.'], Response::HTTP_FORBIDDEN);
        }

        $recipients = array_filter(
            $this->users->findAll(),
            static fn (User $user): bool => in_array($role, $user->rolesIn($code), true),
        );

        return new JsonResponse(array_values(array_map(
            static fn (User $user): array => [
                'name' => $user->getName(),
                'emails' => array_map(
                    static fn (EmailAddress $email): string => $email->getValue(),
                    $user->getNotificationEmails(),
                ),
            ],
            $recipients,
        )));
    }
}
