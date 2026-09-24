<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\NotificationTarget;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserError;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserNotFound;
use App\Portal\Domain\User\UserRepository;

/**
 * Guarda la ficha entera de una persona desde la pantalla de permisos:
 * nombre, si está activa, permisos, segundo correo y a dónde van los avisos.
 *
 * El correo principal no se cambia: es la identidad que da Google. Si alguien
 * cambia de cuenta, es otra persona para la suite (alta nueva y desactivar la
 * vieja).
 */
final readonly class UpdateUser
{
    public function __construct(
        private UserRepository $users,
        private ApplicationCatalog $catalog,
    ) {
    }

    /**
     * @param EmailAddress $actingAdmin quien hace el cambio
     *
     * @throws UserNotFound              si no existe
     * @throws \InvalidArgumentException si el nombre, algún permiso o los avisos no valen
     * @throws UserError                 si alguien intenta quitarse a sí mismo la gestión
     */
    public function __invoke(
        UserId $id,
        string $name,
        bool $active,
        Grants $grants,
        ?EmailAddress $secondaryEmail,
        NotificationTarget $notify,
        EmailAddress $actingAdmin,
    ): User {
        $this->catalog->validate($grants);

        $user = $this->users->find($id) ?? throw UserNotFound::withId($id);

        $keepsAdmin = $active && $grants->has(ApplicationCatalog::PORTAL, ApplicationCatalog::SUITE_ADMIN_ROLE);
        if ($user->getEmail()->equals($actingAdmin) && !$keepsAdmin) {
            throw UserError::cannotDemoteYourself();
        }

        $user->rename($name);
        $user->changeGrants($grants);
        $user->changeContact($secondaryEmail, $notify);
        $active ? $user->reactivate() : $user->deactivate();

        $this->users->save($user);

        return $user;
    }
}
