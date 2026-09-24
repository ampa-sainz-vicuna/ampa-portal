<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Suite;

use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;

/**
 * Construye el catálogo a partir del parámetro `suite.applications`
 * (config/packages/suite.yaml). Symfony lo llama una vez al montar el
 * contenedor de servicios.
 */
final class ApplicationCatalogFactory
{
    /**
     * @param array<string, array{name: string, url?: string, roles: array<string, string>}> $config
     */
    public static function fromConfig(array $config): ApplicationCatalog
    {
        $applications = [];

        foreach ($config as $code => $application) {
            $applications[] = new Application(
                $code,
                $application['name'],
                $application['url'] ?? '',
                $application['roles'],
            );
        }

        return new ApplicationCatalog($applications);
    }
}
