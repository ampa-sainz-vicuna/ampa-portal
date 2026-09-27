<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\Calendar\DefineSchoolYear;
use App\Portal\Domain\Calendar\CalendarError;
use App\Portal\Domain\Calendar\CalendarPeriod;
use App\Portal\Domain\Calendar\DayKind;
use App\Portal\Domain\Calendar\SchoolYear;
use App\Portal\Domain\Calendar\SchoolYearRepository;
use App\Portal\Infrastructure\Http\SchoolYearPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La pantalla *Calendario* del portal, solo para quien gestiona los permisos
 * (access_control, prefijo /api/admin).
 *
 *   GET /api/admin/calendario            los cursos cargados, del más nuevo al más viejo
 *   PUT /api/admin/calendario/2026-2027  guarda el curso entero (crea o sustituye)
 *       {"classesStart", "classesEnd", "periods": [{"from", "to", "kind", "name"}]}
 *
 * Errores: 400 si falta un campo o no tiene la forma que toca, 422 si un valor
 * no vale (el mensaje es para la pantalla).
 */
final readonly class AdminCalendarController
{
    public function __construct(
        private SchoolYearRepository $schoolYears,
        private DefineSchoolYear $defineSchoolYear,
        private SchoolYearPresenter $presenter,
    ) {
    }

    #[Route('/api/admin/calendario', name: 'api_admin_calendar_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map($this->presenter, $this->schoolYears->findAll()));
    }

    #[Route('/api/admin/calendario/{code}', name: 'api_admin_calendar_define', methods: ['PUT'])]
    public function define(string $code, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::error('El cuerpo no es JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($payload)
            || !is_string($payload['classesStart'] ?? null)
            || !is_string($payload['classesEnd'] ?? null)
            || !is_array($payload['periods'] ?? null)) {
            return self::error('Faltan classesStart, classesEnd o periods.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $periods = [];
            foreach ($payload['periods'] as $period) {
                if (!is_array($period)
                    || !is_string($period['from'] ?? null)
                    || !is_string($period['to'] ?? null)
                    || !is_string($period['name'] ?? null)
                    || null === DayKind::tryFrom((string) ($period['kind'] ?? ''))) {
                    return self::error('Cada periodo necesita from, to, name y kind (holiday o non_school).', Response::HTTP_BAD_REQUEST);
                }

                $periods[] = CalendarPeriod::of($period['from'], $period['to'], DayKind::from($period['kind']), $period['name']);
            }

            $schoolYear = ($this->defineSchoolYear)($code, $payload['classesStart'], $payload['classesEnd'], $periods);
        } catch (CalendarError $e) {
            return self::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(($this->presenter)($schoolYear));
    }

    private static function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
