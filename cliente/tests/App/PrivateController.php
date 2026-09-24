<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Tests\App;

use Ampa\PortalCliente\Security\PortalUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Una ruta cualquiera de la aplicación, protegida por el cortafuegos.
 */
final readonly class PrivateController
{
    public function __construct(private Security $security)
    {
    }

    public function private(): JsonResponse
    {
        $user = $this->security->getUser();

        return new JsonResponse([
            'email' => $user?->getUserIdentifier(),
            'roles' => $user?->getRoles(),
            'notificationEmails' => $user instanceof PortalUser ? $user->getNotificationEmails() : null,
        ]);
    }
}
