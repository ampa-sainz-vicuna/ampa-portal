<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Event;

use Ampa\PortalCliente\Security\PortalUser;

/**
 * Alguien acaba de abrir la aplicación: se lanza en cada `GET /api/me`, que
 * es lo primero que pide el front al cargarse (y al recargar la página).
 *
 * Sustituye a "al entrar", que desde el portal ya no pasa en cada aplicación.
 * Fichajes cuelga de aquí lo que haría un cron (cierre del día, días sin
 * fichar…), porque Cloud Run no tiene cron. Como se lanza en cada carga de
 * página y no una vez por sesión, lo que se enganche tiene que ser barato de
 * repetir o saber que ya se hizo.
 *
 * Se escucha con `#[AsEventListener]`. Un fallo en un oyente NO debería
 * impedir abrir la aplicación: que lo recoja y lo registre.
 */
final readonly class ApplicationOpened
{
    public function __construct(private PortalUser $user)
    {
    }

    public function getUser(): PortalUser
    {
        return $this->user;
    }
}
