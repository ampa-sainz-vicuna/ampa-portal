<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Heartbeat;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Comprueba el token del latido preguntándole a Google
 * (https://oauth2.googleapis.com/tokeninfo): si la firma no vale o ha
 * caducado, Google contesta 400; si vale, devuelve lo que dice el token.
 * Aquí se mira además:
 *
 * - que es para esta aplicación (`latido_audiencia`, su dirección pública, la
 *   misma que tiene en el catálogo del portal): un token que Google emitió
 *   para otro servicio no vale aquí;
 * - que es de una cuenta de la suite (`latido_cuentas`): cualquiera con una
 *   cuenta de Google puede pedir un token para la audiencia que quiera.
 *
 * Por qué preguntar a Google y no comprobar la firma aquí, como hace el
 * portal: así el cliente no necesita firebase/php-jwt ni guardar las claves
 * de Google, y cada aplicación no tiene que tocar sus dependencias. Es una
 * llamada al día.
 *
 * Sin configurar (desarrollo) no acepta ninguno.
 */
final readonly class GoogleTokenInfoVerifier implements HeartbeatVerifier
{
    private const string URL = 'https://oauth2.googleapis.com/tokeninfo';
    private const array VALID_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /** @var list<string> */
    private array $accounts;

    /**
     * @param string $accounts correos de las cuentas de servicio admitidas, separados por comas
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $audience,
        string $accounts,
    ) {
        $this->accounts = array_values(array_filter(array_map(
            static fn (string $account): string => mb_strtolower(trim($account)),
            explode(',', $accounts),
        )));
    }

    public function verify(string $token): void
    {
        if ('' === trim($this->audience) || [] === $this->accounts) {
            throw new InvalidHeartbeat('El latido no está configurado en esta aplicación (latido_audiencia, latido_cuentas).');
        }

        if ('' === $token) {
            throw new InvalidHeartbeat('Sin token.');
        }

        try {
            $response = $this->httpClient->request('GET', self::URL, [
                'query' => ['id_token' => $token],
                'timeout' => 10,
            ]);
            $status = $response->getStatusCode();
            $claims = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new InvalidHeartbeat('No se ha podido preguntar a Google por el token.', 0, $e);
        }

        if (200 !== $status) {
            throw new InvalidHeartbeat('Google dice que el token no vale o ha caducado.');
        }

        if (!in_array($claims['iss'] ?? null, self::VALID_ISSUERS, true)) {
            throw new InvalidHeartbeat('El token no lo ha emitido Google.');
        }

        if (($claims['aud'] ?? null) !== rtrim($this->audience, '/')) {
            throw new InvalidHeartbeat('El token no es para esta aplicación.');
        }

        // tokeninfo lo devuelve como texto ("true").
        $verified = $claims['email_verified'] ?? null;
        if (true !== $verified && 'true' !== $verified) {
            throw new InvalidHeartbeat('El correo del token no está verificado.');
        }

        if (!in_array(mb_strtolower((string) ($claims['email'] ?? '')), $this->accounts, true)) {
            throw new InvalidHeartbeat('El token no es de una cuenta de la suite.');
        }
    }
}
