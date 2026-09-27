<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\Heartbeat\RunHeartbeat;
use App\Portal\Infrastructure\Security\InvalidIdentity;
use App\Portal\Infrastructure\Security\ServiceAccountVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * El latido diario de la suite (RunHeartbeat). Lo llama Cloud Scheduler a
 * las 4:00, hora de Madrid (deploy/programar.sh), con el token OIDC de la
 * cuenta de servicio de la suite para la dirección fija del portal: el mismo
 * que ya acepta /api/personas (SUITE_TOKEN_AUDIENCE, SUITE_SERVICE_ACCOUNTS).
 * Una persona no puede lanzarlo, ni siquiera con permiso de administración.
 *
 * Fuera del cortafuegos (security.yaml): no hay cookie de nadie.
 *
 *   POST /api/latido
 *   200 {"steps": [{"step", "outcome": "hecho"|"saltado"|"fallo", "detail"}]}
 *       SIEMPRE 200 aunque falle un paso: si no, Cloud Scheduler lo repetiría
 *       entero. Los fallos van al registro como ERROR (y de ahí, al correo).
 *   401 sin el token de la cuenta de servicio
 */
final readonly class HeartbeatController
{
    public function __construct(
        private ServiceAccountVerifier $serviceAccounts,
        private RunHeartbeat $heartbeat,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/latido', name: 'api_heartbeat', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');

        try {
            $this->serviceAccounts->verify(str_starts_with($header, 'Bearer ') ? substr($header, 7) : '');
        } catch (InvalidIdentity $e) {
            $this->logger->warning('Latido rechazado: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Solo lo llama Cloud Scheduler.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(['steps' => ($this->heartbeat)()->getSteps()]);
    }
}
