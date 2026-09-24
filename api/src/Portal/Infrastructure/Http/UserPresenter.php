<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\User\User;

/**
 * Una persona en la pantalla de permisos.
 */
final readonly class UserPresenter
{
    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     name: string,
     *     active: bool,
     *     grants: object,
     *     secondaryEmail: string|null,
     *     notify: string
     * }
     */
    public function __invoke(User $user): array
    {
        return [
            'id' => $user->getId()->toString(),
            'email' => $user->getEmail()->getValue(),
            'name' => $user->getName(),
            'active' => $user->isActive(),
            // (object): sin permisos sale {} y no [], la misma forma siempre.
            'grants' => (object) $user->getGrants()->toArray(),
            'secondaryEmail' => $user->getSecondaryEmail()?->getValue(),
            'notify' => $user->getNotify()->value,
        ];
    }
}
