<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\Suite\Application;

/**
 * Avisa a una aplicación de que han cambiado los permisos en ella.
 *
 * Nunca lanza: si falla, lo apunta en el registro. Guardar la ficha de la
 * persona ya está hecho y no se puede deshacer por esto; el latido diario de
 * la aplicación lo arregla.
 */
interface GrantsChangeNotifier
{
    public function notify(Application $application): void;
}
