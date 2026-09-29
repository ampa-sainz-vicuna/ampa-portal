<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Domain\Help\FaqRepository;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Http\FaqPresenter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La ayuda que ve quien ha entrado: la pide el botón «Ayuda» de la barra de
 * @ampa/ui, en cada aplicación, desde el navegador (con la cookie de sesión;
 * CORS en HelpCors).
 *
 *   GET /api/ayuda
 *   200 {"notebookUrl", "contactEmail", "updatedAt", "entries": [{"id", "application", "question", "answer", "keywords", "manual"}]}
 *       Solo las preguntas que puede ver (FaqEntry::isVisibleWith). Sin nada
 *       cargado, entries vacía y lo demás null.
 *   401 sin sesión (el cortafuegos, como /api/me)
 *
 * Todas las de todas sus aplicaciones, no solo las de la que la pide: el
 * buscador puede encontrar la respuesta en otra (y así hay una ruta, no una
 * por aplicación). Cada aplicación ordena o filtra por `application` si quiere.
 */
final readonly class HelpController
{
    public function __construct(
        private Security $security,
        private UserRepository $users,
        private FaqRepository $faqs,
        private FaqPresenter $presenter,
    ) {
    }

    #[Route('/api/ayuda', name: 'api_help', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $identifier = $this->security->getUser()?->getUserIdentifier()
            // El cortafuegos ya lo impide (ver SessionController::me).
            ?? throw new \LogicException('No hay ningún usuario autenticado.');
        $user = $this->users->findByEmail(EmailAddress::fromString($identifier))
            ?? throw new \LogicException(sprintf('"%s" ha entrado pero no está en la base de datos.', $identifier));

        return new JsonResponse($this->presenter->forReader($this->faqs->current(), $user->getGrants()));
    }
}
