<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\User\ChangeOwnContact;
use App\Portal\Application\User\RecordVisit;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Http\ContactPayload;
use App\Portal\Infrastructure\Http\SessionPresenter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Quién soy, a qué aplicaciones puedo ir y mis correos. La pantalla del
 * portal lo pide al abrirse para pintar las tarjetas.
 */
final readonly class SessionController
{
    public function __construct(
        private Security $security,
        private UserRepository $users,
        private SessionPresenter $presenter,
        private ChangeOwnContact $changeOwnContact,
        private RecordVisit $recordVisit,
    ) {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $me = $this->me();
        $user = $this->users->findByEmail($me)
            ?? throw new \LogicException(sprintf('"%s" ha entrado pero no está en la base de datos.', $me->getValue()));
        ($this->recordVisit)($user);

        return new JsonResponse(($this->presenter)($user));
    }

    /**
     * Mi segundo correo y a dónde quiero los avisos.
     * JSON: {"secondaryEmail": "…" | null, "notify": "primary" | "secondary" | "both"}.
     * 422 si algo no vale.
     */
    #[Route('/api/me/contacto', name: 'api_me_contact', methods: ['PUT'])]
    public function changeContact(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        try {
            [$secondaryEmail, $notify] = ContactPayload::parse(is_array($payload) ? $payload : []);
            $user = ($this->changeOwnContact)($this->me(), $secondaryEmail, $notify);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(($this->presenter)($user));
    }

    private function me(): EmailAddress
    {
        $identifier = $this->security->getUser()?->getUserIdentifier()
            // El cortafuegos ya lo impide; esto es solo por si alguien cambia la
            // configuración y deja estas rutas abiertas sin darse cuenta.
            ?? throw new \LogicException('No hay ningún usuario autenticado.');

        return EmailAddress::fromString($identifier);
    }
}
