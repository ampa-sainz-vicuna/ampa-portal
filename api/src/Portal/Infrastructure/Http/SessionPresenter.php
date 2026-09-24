<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use App\Portal\Domain\User\User;

/**
 * Quién soy y a dónde puedo ir: lo que devuelven la entrada con Google y
 * `GET /api/me`. Con esto la pantalla del portal pinta una tarjeta por
 * aplicación, "mis correos" y, si es administrador de la suite, la de
 * permisos.
 */
final readonly class SessionPresenter
{
    public function __construct(private ApplicationCatalog $catalog)
    {
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     secondaryEmail: string|null,
     *     notify: string,
     *     isAdmin: bool,
     *     applications: list<array{code: string, name: string, url: string, roles: list<string>}>
     * }
     */
    public function __invoke(User $user): array
    {
        return [
            'name' => $user->getName(),
            'email' => $user->getEmail()->getValue(),
            'secondaryEmail' => $user->getSecondaryEmail()?->getValue(),
            'notify' => $user->getNotify()->value,
            'isAdmin' => $user->isSuiteAdmin(),
            'applications' => array_map(
                static fn (Application $application): array => [
                    'code' => $application->getCode(),
                    'name' => $application->getName(),
                    'url' => $application->getUrl(),
                    'roles' => $user->rolesIn($application->getCode()),
                ],
                $this->catalog->reachableWith($user->getGrants()),
            ),
        ];
    }
}
