<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Las aplicaciones y sus roles, para que la pantalla de permisos pinte una
 * casilla por cada uno. Solo para quien gestiona permisos (/api/admin).
 */
final readonly class AdminApplicationsController
{
    public function __construct(private ApplicationCatalog $catalog)
    {
    }

    #[Route('/api/admin/applications', name: 'api_admin_applications', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (Application $application): array => [
                'code' => $application->getCode(),
                'name' => $application->getName(),
                'roles' => array_map(
                    static fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
                    array_keys($application->getRoles()),
                    array_values($application->getRoles()),
                ),
            ],
            $this->catalog->all(),
        ));
    }
}
