<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Calendar\SchoolYear;
use App\Portal\Domain\Calendar\SchoolYearRepository;
use App\Portal\Infrastructure\Http\SchoolYearPresenter;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * El calendario escolar, abierto y sin sesión, para la web pública
 * (ampa-web), que lo lee al construirse desde GitHub Actions, de servidor a
 * servidor (por eso no lleva CORS).
 *
 * Es público a propósito: el calendario del colegio lo publica la Comunidad
 * de Madrid y no es ningún secreto; aquí solo está ya cargado y revisado por
 * la junta. No expone nada de personas.
 *
 *   GET /api/calendario/publico
 *   200 {"schoolYears": [curso en el que cae hoy y los posteriores, del más
 *       antiguo al más nuevo; nunca los ya pasados; vacío si no hay ninguno]}
 *
 * Cache-Control de una hora: la web se construye pocas veces y el calendario
 * cambia casi nunca.
 */
final readonly class PublicCalendarController
{
    public function __construct(
        private SchoolYearRepository $schoolYears,
        private SchoolYearPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/calendario/publico', name: 'api_calendar_public', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        // En Madrid: en Cloud Run el reloj va en UTC y la noche del 31 de
        // agosto ya sería el curso nuevo dos horas tarde.
        $current = SchoolYear::codeFor($this->clock->now()->setTimezone(new \DateTimeZone('Europe/Madrid')));

        // Los códigos ("2026-2027") se ordenan bien como texto. findAll() los
        // da del más nuevo al más viejo: se descartan los pasados y se invierte.
        $upcoming = array_values(array_filter(
            $this->schoolYears->findAll(),
            static fn (SchoolYear $schoolYear): bool => $schoolYear->getCode() >= $current,
        ));
        usort($upcoming, static fn (SchoolYear $a, SchoolYear $b): int => $a->getCode() <=> $b->getCode());

        $response = new JsonResponse([
            'schoolYears' => array_map($this->presenter, $upcoming),
        ]);
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}
