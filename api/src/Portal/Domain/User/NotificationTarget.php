<?php

declare(strict_types=1);

namespace App\Portal\Domain\User;

/**
 * A qué correo le llegan los avisos de las aplicaciones (una solicitud de
 * vacaciones en fichajes, por ejemplo).
 *
 * Existe por la junta: entran con su cuenta de Workspace del AMPA (que puede
 * ser la del cargo y pasar de una persona a otra), pero muchos prefieren leer
 * los avisos en su correo personal. "Los dos" es lo que hacía fichajes con sus
 * administradores antes del portal.
 */
enum NotificationTarget: string
{
    /** La cuenta con la que entra. */
    case Primary = 'primary';

    /** El segundo correo (normalmente el personal). */
    case Secondary = 'secondary';

    case Both = 'both';
}
