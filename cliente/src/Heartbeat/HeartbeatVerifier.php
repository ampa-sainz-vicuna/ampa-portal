<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Heartbeat;

/**
 * Si quien llama a `POST /api/latido` es de verdad la suite: el token de
 * identidad de Google de su cuenta de servicio, emitido para la dirección de
 * esta aplicación. En producción, GoogleTokenInfoVerifier; en los tests de
 * la aplicación, `Ampa\PortalCliente\Testing\FakeHeartbeatVerifier`.
 */
interface HeartbeatVerifier
{
    /**
     * @throws InvalidHeartbeat
     */
    public function verify(string $token): void;
}
