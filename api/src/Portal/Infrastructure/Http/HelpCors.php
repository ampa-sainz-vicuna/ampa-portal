<?php

declare(strict_types=1);

namespace App\Portal\Infrastructure\Http;

use App\Portal\Domain\Suite\Application;
use App\Portal\Domain\Suite\ApplicationCatalog;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Deja que el front de cada aplicación lea GET /api/ayuda desde su propia
 * web (https://facturacion.ampasainzvicuna.com pide a
 * https://portal.ampasainzvicuna.com): es la única ruta del portal que se
 * llama desde el NAVEGADOR de otra web, con `fetch(…, {credentials:
 * 'include'})`. Las demás las llama el servidor de cada aplicación (sin CORS)
 * o la propia página del portal (mismo origen).
 *
 * La cookie de sesión va sola: es de .ampasainzvicuna.com y las aplicaciones
 * son el mismo sitio, así que `SameSite=Lax` no la frena. Lo que el navegador
 * impide sin estas cabeceras es que la página LEA la respuesta. Por eso solo
 * se da permiso a orígenes conocidos, uno a uno (nunca `*`, que con
 * credenciales no vale y dejaría a cualquier web leer la ayuda de quien la
 * visita): las aplicaciones del catálogo (sus URL_… de suite.yaml), el propio
 * portal (DEFAULT_URI) y los de HELP_ALLOWED_ORIGINS (en desarrollo, los Vite
 * de cada aplicación; en producción, vacía).
 *
 * Un listener pequeño en vez de un bundle de CORS: es una ruta, de lectura.
 * CrossSiteRequestGuard no la frena: solo mira los métodos que cambian algo.
 */
final readonly class HelpCors
{
    private const string PATH = '/api/ayuda';

    /** @var array<string, true> */
    private array $allowed;

    public function __construct(ApplicationCatalog $catalog, string $portalUrl, string $extraOrigins)
    {
        $urls = [
            $portalUrl,
            ...array_map(static fn (Application $application): string => $application->getUrl(), $catalog->all()),
            ...explode(',', $extraOrigins),
        ];

        $allowed = [];
        foreach ($urls as $url) {
            $origin = self::originOf($url);
            if (null !== $origin) {
                $allowed[$origin] = true;
            }
        }

        $this->allowed = $allowed;
    }

    /**
     * La petición previa (OPTIONS) se contesta aquí, antes del router (que
     * daría 405: la ruta solo es GET) y del cortafuegos (que daría 401: el
     * navegador no manda la cookie en la previa). Con un GET y sin cabeceras
     * raras el navegador no la hace, pero @ampa/ui podría añadir alguna.
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || self::PATH !== $request->getPathInfo() || !$request->isMethod('OPTIONS')) {
            return;
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);

        if ($this->isAllowed($request)) {
            $response->headers->set('Access-Control-Allow-Methods', 'GET');
            $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
            $response->headers->set('Access-Control-Max-Age', '600');
        }

        $event->setResponse($response);
    }

    /**
     * Todas las respuestas de la ruta, también el 401: sin las cabeceras, la
     * página no podría ni saber que es un 401 (el navegador lo convierte en
     * un error de red) y no podría decir "vuelve a entrar".
     */
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || self::PATH !== $request->getPathInfo()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        // Siempre, se permita o no: la respuesta depende del Origin, y una
        // caché intermedia no debe dar la de un origen a otro.
        $headers->set('Vary', 'Origin', false);

        if ($this->isAllowed($request)) {
            $headers->set('Access-Control-Allow-Origin', (string) $request->headers->get('Origin'));
            $headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function isAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return null !== $origin && isset($this->allowed[$origin]);
    }

    /**
     * "https://facturacion.ampasainzvicuna.com/lo-que-sea" → "https://facturacion.ampasainzvicuna.com",
     * como lo escribe el navegador en Origin: esquema y host en minúsculas y
     * el puerto solo si no es el de siempre. null si no es una dirección.
     */
    private static function originOf(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;
        $default = ['http' => 80, 'https' => 443][$scheme] ?? null;

        return sprintf('%s://%s%s', $scheme, strtolower($parts['host']), null === $port || $port === $default ? '' : ':'.$port);
    }
}
