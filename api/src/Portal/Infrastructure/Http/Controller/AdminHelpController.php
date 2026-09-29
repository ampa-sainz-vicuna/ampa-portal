<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\Help\FaqFile;
use App\Portal\Application\Help\LoadFaq;
use App\Portal\Domain\Help\FaqError;
use App\Portal\Domain\Help\FaqRepository;
use App\Portal\Infrastructure\Http\FaqPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La pantalla *Ayuda* del portal, solo para quien gestiona los permisos
 * (access_control, prefijo /api/admin).
 *
 *   GET /api/admin/ayuda  qué hay cargado (FaqPresenter::forAdmin)
 *   PUT /api/admin/ayuda  el fichero faq.json entero: sustituye toda la ayuda
 *                         y devuelve lo mismo que el GET
 *
 * Errores: 400 si el cuerpo no es JSON, 422 si el fichero no vale (el mensaje
 * dice qué pregunta y por qué, para arreglarlo).
 */
final readonly class AdminHelpController
{
    public function __construct(
        private FaqRepository $faqs,
        private LoadFaq $loadFaq,
        private FaqPresenter $presenter,
    ) {
    }

    #[Route('/api/admin/ayuda', name: 'api_admin_help_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return new JsonResponse($this->presenter->forAdmin($this->faqs->current()));
    }

    #[Route('/api/admin/ayuda', name: 'api_admin_help_load', methods: ['PUT'])]
    public function load(Request $request): JsonResponse
    {
        try {
            $file = json_decode($request->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'El fichero no es JSON.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $faq = ($this->loadFaq)(FaqFile::parse($file));
        } catch (FaqError $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->presenter->forAdmin($faq));
    }
}
