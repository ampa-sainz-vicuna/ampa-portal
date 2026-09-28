<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http\Controller;

use App\Portal\Application\User\RecordVisit;
use App\Portal\Domain\User\EmailAddress;
use App\Portal\Domain\User\User;
use App\Portal\Domain\User\UserRepository;
use App\Portal\Infrastructure\Http\SessionPresenter;
use App\Portal\Infrastructure\Security\IdentityVerifier;
use App\Portal\Infrastructure\Security\InvalidIdentity;
use App\Portal\Infrastructure\Security\SessionCookie;
use App\Portal\Infrastructure\Security\SessionTokens;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entrar y salir de la suite.
 *
 * Entrar es canjear una identidad de Google por la cookie de sesión. Es el
 * único sitio de la suite que recibe algo de Google.
 */
final readonly class AuthController
{
    /** Lo que se devuelve a la página en `?entrada=` al volver de Google. */
    public const string RETURN_OK = 'ok';
    public const string RETURN_NO_ACCESS = 'sin-acceso';
    public const string RETURN_INVALID = 'no-valida';
    public const string RETURN_EXPIRED = 'caducada';

    /** La cookie que pone el script de Google antes de ir a Google (5 minutos). */
    private const string GOOGLE_CSRF_COOKIE = 'g_csrf_token';

    public function __construct(
        private IdentityVerifier $identityVerifier,
        private UserRepository $users,
        private SessionTokens $tokens,
        private SessionCookie $cookie,
        private SessionPresenter $presenter,
        private ClockInterface $clock,
        private RecordVisit $recordVisit,
    ) {
    }

    /**
     * JSON: {"credential": "<ID token de Google>"}. La usaba el botón en modo
     * ventana emergente; se queda para quien tenga abierta una página vieja.
     *
     * 200 con quién es y la cookie puesta; 401 si Google no lo confirma; 403
     * si no tiene acceso a nada.
     */
    #[Route('/api/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function signIn(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $credential = $payload['credential'] ?? null;

        if (!is_string($credential) || '' === $credential) {
            return new JsonResponse(
                ['error' => 'Falta el campo "credential" con el token de Google.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $user = $this->userFor($credential);
        } catch (InvalidIdentity|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        if (null === $user) {
            return new JsonResponse(
                ['error' => 'Esa cuenta no tiene acceso a ninguna aplicación del AMPA.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $this->withSession(new JsonResponse(($this->presenter)($user)), $user);
    }

    /**
     * La vuelta de Google en modo redirección (`login_uri` del botón): la
     * página entera se fue a Google y Google la devuelve aquí con un POST de
     * formulario (`credential`, `g_csrf_token` y `state`).
     *
     * Existe porque en algunos móviles la ventana emergente de Google se
     * quedaba en blanco (27/09/2026). Así no hay ventana que se cuelgue.
     *
     * Contesta siempre llevando a la página del portal con `?entrada=` (ok,
     * sin-acceso, no-valida o caducada), para que sea ella la que lo explique,
     * y con `?volver=` si la página lo mandó en `state` (la aplicación de la
     * que venía; la página comprueba que sea de las suyas).
     *
     * El POST viene de accounts.google.com, así que CrossSiteRequestGuard lo
     * deja pasar; la barrera contra CSRF aquí es la de Google: la cookie
     * `g_csrf_token` que puso su script en esta página tiene que coincidir con
     * el campo del mismo nombre.
     */
    #[Route('/api/auth/google/vuelta', name: 'api_auth_google_return', methods: ['POST'])]
    public function signInReturn(Request $request): RedirectResponse
    {
        $state = $request->request->get('state');
        $returnTo = is_string($state) && '' !== $state && strlen($state) <= 2000 ? $state : null;

        $csrfCookie = $request->cookies->get(self::GOOGLE_CSRF_COOKIE);
        $csrfField = $request->request->get(self::GOOGLE_CSRF_COOKIE);

        if (!is_string($csrfCookie) || '' === $csrfCookie || !is_string($csrfField) || !hash_equals($csrfCookie, $csrfField)) {
            return $this->backToPage(self::RETURN_EXPIRED, $returnTo);
        }

        $credential = $request->request->get('credential');

        if (!is_string($credential) || '' === $credential) {
            return $this->backToPage(self::RETURN_INVALID, $returnTo);
        }

        try {
            $user = $this->userFor($credential);
        } catch (InvalidIdentity|\InvalidArgumentException) {
            return $this->backToPage(self::RETURN_INVALID, $returnTo);
        }

        if (null === $user) {
            return $this->backToPage(self::RETURN_NO_ACCESS, $returnTo);
        }

        return $this->withSession($this->backToPage(self::RETURN_OK, $returnTo), $user);
    }

    /**
     * La misma dirección pedida con GET: no es Google devolviendo a nadie (eso
     * siempre es un POST), sino un robot de Google que la visita justo después
     * de entrar (visto en los registros el 28/09/2026) o alguien que la tiene
     * en el historial. A la portada, que es donde quería ir, en vez de un 405.
     */
    #[Route('/api/auth/google/vuelta', name: 'api_auth_google_return_visited', methods: ['GET'])]
    public function signInReturnVisited(): RedirectResponse
    {
        return new RedirectResponse('/', Response::HTTP_SEE_OTHER);
    }

    /**
     * Borra la cookie. Sale de toda la suite a la vez: la sesión es una sola.
     * No hace falta haber entrado (borrar una cookie que no hay no molesta).
     */
    #[Route('/api/auth/salir', name: 'api_auth_sign_out', methods: ['POST'])]
    public function signOut(): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookie->clear());

        return $response;
    }

    /**
     * Que Google confirme quién eres no da acceso: hace falta estar dado de
     * alta, activo y con permiso en alguna aplicación. Cada aplicación lo
     * vuelve a comprobar en cada petición.
     *
     * @return User|null null si la cuenta es buena pero no tiene acceso a nada
     *
     * @throws InvalidIdentity|\InvalidArgumentException si Google no lo confirma
     */
    private function userFor(string $credential): ?User
    {
        $email = EmailAddress::fromString($this->identityVerifier->verify($credential)->getEmail());
        $user = $this->users->findByEmail($email);

        return null !== $user && $user->canSignIn() ? $user : null;
    }

    /**
     * El token va SOLO en la cookie (HttpOnly), no en el cuerpo: así el
     * JavaScript de ninguna página llega a tenerlo.
     *
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    private function withSession(Response $response, User $user): Response
    {
        ($this->recordVisit)($user);

        $response->headers->setCookie($this->cookie->create(
            $this->tokens->issue($user->getEmail()),
            $this->tokens->getTtl(),
            $this->clock->now(),
        ));

        return $response;
    }

    /**
     * A la portada del portal, en el mismo origen por el que se entró. 303:
     * el navegador la pide con GET, no repite el POST.
     */
    private function backToPage(string $outcome, ?string $returnTo): RedirectResponse
    {
        $query = ['entrada' => $outcome];

        if (null !== $returnTo) {
            $query['volver'] = $returnTo;
        }

        return new RedirectResponse('/?'.http_build_query($query), Response::HTTP_SEE_OTHER);
    }
}
