<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Portal;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * El token de identidad de Google de la cuenta de servicio con la que corre
 * este servidor en Cloud Run, emitido para el portal.
 *
 * No hay claves que guardar: dentro de Cloud Run, Google ofrece un "servidor
 * de metadatos" en una dirección interna que firma, a quien lo pida desde el
 * propio contenedor, un token que dice "soy tal cuenta de servicio" para la
 * AUDIENCIA que se le pida. La audiencia es la dirección del portal (la misma
 * `portal_url`, su dirección fija de Cloud Run) y el portal solo acepta esa
 * (SUITE_TOKEN_AUDIENCE en el portal): un token para otro servicio no le vale.
 *
 * Fuera de Google Cloud esa dirección no existe: en desarrollo, preguntar sin
 * sesión da PortalUnavailable.
 */
final class MetadataServerIdentity implements ApplicationIdentity
{
    private const string URL = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/identity';

    /** Dura una hora; basta con no pedirlo dos veces en la misma petición. */
    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $portalUrl,
    ) {
    }

    public function token(): string
    {
        return $this->token ??= $this->fetch();
    }

    private function fetch(): string
    {
        try {
            $response = $this->httpClient->request('GET', self::URL, [
                'headers' => ['Metadata-Flavor' => 'Google'],
                // "full" añade el correo de la cuenta al token: el portal lo
                // mira para saber que es una de la suite.
                'query' => ['audience' => rtrim($this->portalUrl, '/'), 'format' => 'full'],
                'timeout' => 5,
            ]);

            $token = trim($response->getContent());
        } catch (ExceptionInterface $e) {
            throw new PortalUnavailable('No se ha podido pedir a Google el token de este servidor (solo funciona dentro de Cloud Run).', 0, $e);
        }

        if ('' === $token) {
            throw new PortalUnavailable('Google no ha devuelto ningún token para este servidor.');
        }

        return $token;
    }
}
