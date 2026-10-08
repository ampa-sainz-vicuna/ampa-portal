<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\Board\AppointBoardMember;
use App\Portal\Application\Board\EndBoardTerm;
use App\Portal\Application\Board\LinkBoardMemberUser;
use App\Portal\Application\Board\ListBoard;
use App\Portal\Application\Board\UpdateBoardMember;
use App\Portal\Domain\Board\BoardError;
use App\Portal\Domain\Board\BoardMemberId;
use App\Portal\Domain\Board\BoardMemberNotFound;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserNotFound;
use App\Portal\Infrastructure\Http\BoardPayload;
use App\Portal\Infrastructure\Http\BoardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La pestaña *Junta* del portal, solo para quien gestiona los permisos (lo
 * garantiza access_control con el prefijo /api/admin, igual que la pantalla de
 * permisos). Aquí sí salen los datos personales.
 *
 *   GET  /api/admin/junta                      {"active": [...], "past": [...]}
 *   POST /api/admin/junta                      alta (201)
 *   PUT  /api/admin/junta/{id}                 edita la ficha entera
 *   POST /api/admin/junta/{id}/baja            {"endDate": "AAAA-MM-DD"} (opcional: hoy)
 *   PUT  /api/admin/junta/{id}/usuario         {"userId": "…" | null}
 *
 * Errores: 400 si falta un campo o no es del tipo que toca, 422 si un valor no
 * vale (DNI, teléfono, fecha…), 404 si el cargo o el usuario no existen, 409 si
 * el cargo ya tiene titular o ya estaba dado de baja. Ningún mensaje lleva el
 * DNI ni la dirección.
 */
final readonly class AdminBoardController
{
    public function __construct(
        private ListBoard $listBoard,
        private AppointBoardMember $appoint,
        private UpdateBoardMember $update,
        private EndBoardTerm $endTerm,
        private LinkBoardMemberUser $linkUser,
        private BoardPresenter $presenter,
    ) {
    }

    #[Route('/api/admin/junta', name: 'api_admin_board_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'active' => array_map($this->presenter, $this->listBoard->active()),
            'past' => array_map($this->presenter, $this->listBoard->past()),
        ]);
    }

    #[Route('/api/admin/junta', name: 'api_admin_board_appoint', methods: ['POST'])]
    public function appoint(Request $request): JsonResponse
    {
        try {
            $member = ($this->appoint)(BoardPayload::parse(self::payload($request)));
        } catch (\Throwable $e) {
            return self::failure($e);
        }

        return new JsonResponse(($this->presenter)($member), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/junta/{id}', name: 'api_admin_board_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $member = ($this->update)(self::id($id), BoardPayload::parse(self::payload($request)));
        } catch (\Throwable $e) {
            return self::failure($e);
        }

        return new JsonResponse(($this->presenter)($member));
    }

    #[Route('/api/admin/junta/{id}/baja', name: 'api_admin_board_end', methods: ['POST'])]
    public function end(string $id, Request $request): JsonResponse
    {
        try {
            $endDate = self::payload($request)['endDate'] ?? null;
            if (null !== $endDate && !is_string($endDate)) {
                throw new \UnexpectedValueException('"endDate" tiene que ser un texto AAAA-MM-DD.');
            }

            // Sin fecha, hoy. Hora de Madrid: Cloud Run va en UTC y de madrugada saldría ayer.
            $date = null === $endDate
                ? new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'))
                : BoardPayload::date($endDate, 'La fecha de fin');

            $member = ($this->endTerm)(self::id($id), $date);
        } catch (\Throwable $e) {
            return self::failure($e);
        }

        return new JsonResponse(($this->presenter)($member));
    }

    #[Route('/api/admin/junta/{id}/usuario', name: 'api_admin_board_link_user', methods: ['PUT'])]
    public function link(string $id, Request $request): JsonResponse
    {
        try {
            $payload = self::payload($request);
            if (!array_key_exists('userId', $payload) || (null !== $payload['userId'] && !is_string($payload['userId']))) {
                throw new \UnexpectedValueException('Hace falta "userId": el identificador del usuario o null.');
            }

            $member = ($this->linkUser)(
                self::id($id),
                null === $payload['userId'] ? null : UserId::fromString($payload['userId']),
            );
        } catch (\Throwable $e) {
            return self::failure($e);
        }

        return new JsonResponse(($this->presenter)($member));
    }

    /** Un identificador mal formado es un cargo que no existe. */
    private static function id(string $id): BoardMemberId
    {
        try {
            return BoardMemberId::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new \OutOfBoundsException('No hay ningún cargo con ese identificador.');
        }
    }

    /**
     * Traduce los fallos esperados; cualquier otro se relanza (500, y Symfony lo
     * registra: por eso los mensajes propios no llevan datos personales).
     */
    private static function failure(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof \UnexpectedValueException => self::error($e->getMessage(), Response::HTTP_BAD_REQUEST),
            $e instanceof BoardMemberNotFound,
            $e instanceof UserNotFound,
            $e instanceof \OutOfBoundsException => self::error($e->getMessage(), Response::HTTP_NOT_FOUND),
            $e instanceof \InvalidArgumentException => self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof BoardError => self::error($e->getMessage(), Response::HTTP_CONFLICT),
            default => throw $e,
        };
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
