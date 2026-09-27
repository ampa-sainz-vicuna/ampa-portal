<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Http;

use Ampa\PortalCliente\Event\HeartbeatReceived;
use Ampa\PortalCliente\Heartbeat\HeartbeatVerifier;
use Ampa\PortalCliente\Heartbeat\InvalidHeartbeat;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * `POST /api/latido`: el portal despierta a la aplicación cada noche (desde
 * la 0.1.5). Comprueba que llama la suite y lanza HeartbeatReceived para que
 * la aplicación haga su trabajo programado.
 *
 *   200 {"ok": true}
 *   401 no es la suite (o la aplicación no tiene configurado el latido)
 *   500 un oyente ha fallado: el portal lo registra como error
 *
 * En security.yaml de la aplicación va como `PUBLIC_ACCESS` (README, paso
 * 5): no hay cookie de nadie; quien llama lo comprueba HeartbeatVerifier.
 */
final readonly class HeartbeatController
{
    public function __construct(
        private HeartbeatVerifier $verifier,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');

        try {
            $this->verifier->verify(str_starts_with($header, 'Bearer ') ? substr($header, 7) : '');
        } catch (InvalidHeartbeat $e) {
            $this->logger->warning('Latido rechazado: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Solo lo llama el portal.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->dispatcher->dispatch(new HeartbeatReceived(new \DateTimeImmutable()));
        } catch (\Throwable $e) {
            $this->logger->error('Latido: el trabajo programado ha fallado ({error}).', ['error' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['ok' => true]);
    }
}
