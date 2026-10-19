<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\User;

/**
 * Decide a qué aplicaciones hay que avisar tras cambiar a una persona: las que
 * piden avisos (`avisos_permisos` en suite.yaml) y en las que sus roles han
 * cambiado.
 *
 * Se compara lo que la persona PUEDE hacer (User::rolesIn), no lo que está
 * guardado: desactivarla vacía todos sus roles, y eso también hay que
 * contárselo a la aplicación. El orden de los roles no cuenta.
 *
 * Los casos de uso toman la foto ANTES de modificar a la persona (el agregado
 * se cambia en el sitio) y avisan después de guardarla.
 */
final readonly class GrantsChangeAnnouncer
{
    public function __construct(
        private ApplicationCatalog $catalog,
        private GrantsChangeNotifier $notifier,
    ) {
    }

    /**
     * Los roles de la persona en cada aplicación que pide avisos.
     *
     * @return array<string, list<string>> código → roles; vacío si no existía
     */
    public function snapshot(?User $user): array
    {
        $roles = [];
        if (null === $user) {
            return $roles;
        }

        foreach ($this->catalog->all() as $application) {
            if ($application->receivesGrantNotices()) {
                $roles[$application->getCode()] = $user->rolesIn($application->getCode());
            }
        }

        return $roles;
    }

    /**
     * @param array<string, list<string>> $rolesBefore lo que devolvió snapshot() antes del cambio; vacío si la persona es nueva
     */
    public function announce(array $rolesBefore, User $after): void
    {
        foreach ($this->catalog->all() as $application) {
            if (!$application->receivesGrantNotices()) {
                continue;
            }

            $code = $application->getCode();
            $before = $rolesBefore[$code] ?? [];
            $now = $after->rolesIn($code);
            sort($before);
            sort($now);

            if ($before !== $now) {
                $this->notifier->notify($application);
            }
        }
    }
}
