<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\Board\ListBoard;
use App\Portal\Infrastructure\Http\PublicBoardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Quién está hoy en la junta, para cualquier persona con sesión (access_control:
 * IS_AUTHENTICATED). Pensado para que otras aplicaciones sepan quién es la
 * secretaria, la tesorera…
 *
 *   GET /api/junta
 *   200 [{"firstName", "lastName", "email", "position"}]   solo cargos activos,
 *       presidencia primero. Sin DNI, dirección ni teléfono.
 *
 * Todavía no está en el cliente (`cliente/`): hacerlo cambia su contrato.
 */
final readonly class BoardController
{
    public function __construct(
        private ListBoard $listBoard,
        private PublicBoardPresenter $presenter,
    ) {
    }

    #[Route('/api/junta', name: 'api_board', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map($this->presenter, $this->listBoard->active()));
    }
}
