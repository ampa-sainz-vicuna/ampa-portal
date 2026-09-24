<?php

declare(strict_types=1);

namespace App\Portal\Domain\Suite;

use App\Portal\Domain\User\Grants;

/**
 * Las aplicaciones de la suite y sus roles.
 *
 * Están en la configuración (`config/packages/suite.yaml`) y no en la base de
 * datos a propósito: una aplicación nueva, o un rol nuevo, llega siempre con
 * código nuevo en esa aplicación, así que no tiene sentido darlo de alta desde
 * una pantalla. Y en la configuración queda versionado.
 *
 * El propio portal es una aplicación más, con un rol: `admin`, quien gestiona
 * los permisos de toda la suite.
 */
final readonly class ApplicationCatalog
{
    public const string PORTAL = 'portal';
    public const string SUITE_ADMIN_ROLE = 'admin';

    /** @var array<string, Application> */
    private array $applications;

    /**
     * @param list<Application> $applications en el orden en que se enseñan
     */
    public function __construct(array $applications)
    {
        $byCode = [];
        foreach ($applications as $application) {
            $byCode[$application->getCode()] = $application;
        }

        // Sin esto nadie podría gestionar permisos. Mejor que falle al arrancar
        // que descubrirlo cuando haga falta dar un acceso.
        if (!($byCode[self::PORTAL] ?? null)?->hasRole(self::SUITE_ADMIN_ROLE)) {
            throw new \InvalidArgumentException(sprintf(
                'El catálogo tiene que incluir la aplicación "%s" con el rol "%s".',
                self::PORTAL,
                self::SUITE_ADMIN_ROLE,
            ));
        }

        $this->applications = $byCode;
    }

    /** @return list<Application> */
    public function all(): array
    {
        return array_values($this->applications);
    }

    public function find(string $code): ?Application
    {
        return $this->applications[$code] ?? null;
    }

    /**
     * Las aplicaciones en las que entra quien tiene estos permisos, en el
     * orden del catálogo. Sin el portal: es donde ya está.
     *
     * @return list<Application>
     */
    public function reachableWith(Grants $grants): array
    {
        return array_values(array_filter(
            $this->applications,
            static fn (Application $application): bool => self::PORTAL !== $application->getCode()
                && [] !== $grants->rolesIn($application->getCode()),
        ));
    }

    /**
     * @throws \InvalidArgumentException si alguna aplicación o rol no existe
     */
    public function validate(Grants $grants): void
    {
        foreach ($grants->toArray() as $code => $roles) {
            $application = $this->find($code);

            if (null === $application) {
                throw new \InvalidArgumentException(sprintf('No existe la aplicación "%s".', $code));
            }

            foreach ($roles as $role) {
                if (!$application->hasRole($role)) {
                    throw new \InvalidArgumentException(sprintf('La aplicación "%s" no tiene el rol "%s".', $code, $role));
                }
            }
        }
    }
}
