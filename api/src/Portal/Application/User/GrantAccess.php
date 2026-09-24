<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\Grants;
use App\Portal\Domain\User\NotificationTarget;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserId;
use App\Portal\Domain\User\UserRepository;

/**
 * Añade permisos a una persona, dándola de alta si no existe.
 *
 * Es lo que usa el comando de consola `app:permisos:dar`, que sirve para lo
 * que la pantalla no puede: dar el PRIMER administrador (sin él nadie entra a
 * la pantalla) y pasar al portal los accesos que ya existían en fichajes y
 * listados. Solo suma: nunca quita un permiso que ya tuviera.
 */
final readonly class GrantAccess
{
    public function __construct(
        private UserRepository $users,
        private ApplicationCatalog $catalog,
    ) {
    }

    /**
     * @param string|null             $name           obligatorio si la persona no existe; si existe y viene, se le cambia
     * @param EmailAddress|null       $secondaryEmail si viene, se le cambia (con $notify)
     * @param NotificationTarget|null $notify         si no viene: a los dos correos si hay segundo, al principal si no
     *
     * @throws \InvalidArgumentException si falta el nombre de alguien nuevo, o algún permiso o los avisos no valen
     */
    public function __invoke(
        EmailAddress $email,
        ?string $name,
        Grants $grants,
        ?EmailAddress $secondaryEmail = null,
        ?NotificationTarget $notify = null,
    ): User {
        $this->catalog->validate($grants);

        $user = $this->users->findByEmail($email);

        if (null === $user) {
            $user = User::register(
                UserId::generate(),
                $email,
                $name ?? throw new \InvalidArgumentException(sprintf('"%s" no está dado de alta: hace falta su nombre.', $email->getValue())),
                $grants,
            );
        } else {
            $merged = $user->getGrants();
            foreach ($grants->toArray() as $application => $roles) {
                foreach ($roles as $role) {
                    $merged = $merged->with($application, $role);
                }
            }

            $user->changeGrants($merged);
            if (null !== $name) {
                $user->rename($name);
            }
        }

        // "Los dos" por defecto con segundo correo: es lo que hacía fichajes
        // con sus administradores, y así pasarlos al portal no cambia a dónde
        // les llegan los avisos.
        if (null !== $secondaryEmail || null !== $notify) {
            $user->changeContact(
                $secondaryEmail ?? $user->getSecondaryEmail(),
                $notify ?? (null !== $secondaryEmail ? NotificationTarget::Both : NotificationTarget::Primary),
            );
        }

        $this->users->save($user);

        return $user;
    }
}
