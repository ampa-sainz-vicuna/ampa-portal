<?php

declare(strict_types=1);

namespace App\Portal\Application\User;

use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\NotificationTarget;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;

/**
 * Cada persona elige su segundo correo y a dónde le llegan los avisos, sin
 * pedírselo a quien gestiona los permisos. Es lo único de su ficha que puede
 * tocar ella misma.
 */
final readonly class ChangeOwnContact
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @throws \InvalidArgumentException si pide avisos en un segundo correo que no tiene
     */
    public function __invoke(EmailAddress $me, ?EmailAddress $secondaryEmail, NotificationTarget $notify): User
    {
        $user = $this->users->findByEmail($me)
            ?? throw new \LogicException(sprintf('"%s" ha entrado pero no está en la base de datos.', $me->getValue()));

        $user->changeContact($secondaryEmail, $notify);
        $this->users->save($user);

        return $user;
    }
}
