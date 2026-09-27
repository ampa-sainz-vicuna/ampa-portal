<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Calendar\SchoolYearRepository;
use App\Portal\Infrastructure\Http\SchoolYearPresenter;
use App\Portal\Infrastructure\Security\BearerCaller;
use App\Portal\Infrastructure\Security\InvalidSessionToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * El calendario de un curso, para las aplicaciones (cliente 0.1.5,
 * SuiteCalendar): fichajes para los festivos, facturación para los días
 * lectivos de los desayunos.
 *
 * Como /api/personas, la llama el servidor de la aplicación con el token de
 * quien hace la petición o, sin nadie detrás, con el de la cuenta de servicio
 * de la suite (BearerCaller). No hace falta ningún rol: el calendario del
 * colegio no es un secreto, basta con ser alguien de la suite.
 *
 *   GET /api/calendario?curso=2026-2027
 *   200 el curso (SchoolYearPresenter)
 *   401 sin sesión válida · 404 ese curso no está cargado todavía
 */
final readonly class CalendarController
{
    public function __construct(
        private BearerCaller $bearerCaller,
        private SchoolYearRepository $schoolYears,
        private SchoolYearPresenter $presenter,
    ) {
    }

    #[Route('/api/calendario', name: 'api_calendar', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->bearerCaller->from($request);
        } catch (InvalidSessionToken $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        $code = (string) $request->query->get('curso', '');
        $schoolYear = $this->schoolYears->find($code);

        if (null === $schoolYear) {
            return new JsonResponse(['error' => sprintf('El calendario del curso "%s" no está cargado en el portal.', $code)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(($this->presenter)($schoolYear));
    }
}
