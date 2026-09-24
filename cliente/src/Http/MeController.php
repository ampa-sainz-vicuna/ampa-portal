<?php

declare(strict_types=1);

namespace Ampa\PortalCliente\Http;

use Ampa\PortalCliente\Event\ApplicationOpened;
use Ampa\PortalCliente\Security\PortalUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * `GET /api/me`: quién soy en esta aplicación. Es la mitad del contrato con
 * el front (`SessionGate` de @ampa/ui): `{ name, email, …lo que añada la
 * aplicación }`.
 *
 * Solo sirve para que el front decida qué enseñar. Quien de verdad impide
 * entrar donde no toca es access_control en el security.yaml de la aplicación.
 */
final readonly class MeController
{
    public function __construct(
        private Security $security,
        private MeExtension $extension,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof PortalUser) {
            // El cortafuegos ya lo impide; esto es solo por si alguien cambia la
            // configuración y deja esta ruta abierta sin darse cuenta.
            throw new \LogicException('No hay ningún usuario del portal autenticado.');
        }

        $this->dispatcher->dispatch(new ApplicationOpened($user));

        return new JsonResponse([
            'name' => $user->getName(),
            'email' => $user->getUserIdentifier(),
            ...$this->extension->describe($user),
        ]);
    }
}
