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
use App\Portal\Domain\User\UserRepository;

/**
 * Da de alta a una persona con sus permisos y, si se sabe ya, su segundo
 * correo.
 */
final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private ApplicationCatalog $catalog,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si el correo, el nombre, algún permiso o los avisos no valen
     * @throws UserError                 si el correo ya está dado de alta
     */
    public function __invoke(
        EmailAddress $email,
        string $name,
        Grants $grants,
        ?EmailAddress $secondaryEmail = null,
        NotificationTarget $notify = NotificationTarget::Primary,
    ): User {
        $this->catalog->validate($grants);

        if (null !== $this->users->findByEmail($email)) {
            throw UserError::alreadyRegistered($email);
        }

        $user = User::register(UserId::generate(), $email, $name, $grants);
        $user->changeContact($secondaryEmail, $notify);
        $this->users->save($user);

        return $user;
    }
}
