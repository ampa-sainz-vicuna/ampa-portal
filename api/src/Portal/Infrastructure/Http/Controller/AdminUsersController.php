<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\User\RegisterUser;
use App\Portal\Application\User\UpdateUser;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\UserError;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserNotFound;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Http\ContactPayload;
use App\Portal\Infrastructure\Http\UserPresenter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La pantalla de permisos de la suite, solo para quien los gestiona: lo
 * garantiza access_control con el prefijo /api/admin.
 *
 * Errores: 400 si falta un campo o no tiene la forma que toca, 422 si un valor
 * no vale (correo, nombre, aplicación o rol que no existe, avisos a un segundo
 * correo que no hay), 404 si la persona no existe, 409 si choca con lo que ya
 * hay.
 */
final readonly class AdminUsersController
{
    public function __construct(
        private UserRepository $users,
        private RegisterUser $registerUser,
        private UpdateUser $updateUser,
        private UserPresenter $presenter,
        private Security $security,
    ) {
    }

    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map($this->presenter, $this->users->findAll()));
    }

    /**
     * JSON: {"email": "…", "name": "…", "grants": {"listados": ["usuario"]},
     *        "secondaryEmail": "…" | null, "notify": "primary"}.
     * Los dos últimos son opcionales.
     */
    #[Route('/api/admin/users', name: 'api_admin_users_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = self::payload($request);
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? null;
        $grants = $payload['grants'] ?? [];

        if (!is_string($email) || !is_string($name) || !is_array($grants)) {
            return self::error('Hacen falta el correo ("email"), el nombre ("name") y los permisos ("grants").', Response::HTTP_BAD_REQUEST);
        }

        try {
            [$secondaryEmail, $notify] = ContactPayload::parse($payload);
            $user = ($this->registerUser)(EmailAddress::fromString($email), $name, Grants::fromArray($grants), $secondaryEmail, $notify);
        } catch (\InvalidArgumentException $e) {
            return self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UserError $e) {
            return self::error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(($this->presenter)($user), Response::HTTP_CREATED);
    }

    /**
     * JSON: {"name": "…", "active": true, "grants": {…},
     *        "secondaryEmail": "…" | null, "notify": "…"}.
     * Sustituye la ficha entera: los permisos que no vengan, se quitan, y sin
     * segundo correo se queda sin él.
     */
    #[Route('/api/admin/users/{id}', name: 'api_admin_users_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = self::payload($request);
        $name = $payload['name'] ?? null;
        $active = $payload['active'] ?? null;
        $grants = $payload['grants'] ?? null;

        if (!is_string($name) || !is_bool($active) || !is_array($grants)) {
            return self::error('Hacen falta el nombre ("name"), si está activo ("active") y los permisos ("grants").', Response::HTTP_BAD_REQUEST);
        }

        $actingAdmin = EmailAddress::fromString((string) $this->security->getUser()?->getUserIdentifier());

        try {
            [$secondaryEmail, $notify] = ContactPayload::parse($payload);
            $user = ($this->updateUser)(
                UserId::fromString($id),
                $name,
                $active,
                Grants::fromArray($grants),
                $secondaryEmail,
                $notify,
                $actingAdmin,
            );
        } catch (UserNotFound $e) {
            return self::error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UserError $e) {
            return self::error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return new JsonResponse(($this->presenter)($user));
    }

    /** @return array<string, mixed> */
    private static function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
