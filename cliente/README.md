# ampa/portal-cliente

El cliente del portal del AMPA para las aplicaciones Symfony de la suite
(fichajes, listados, facturación…). Con él, una aplicación **no habla con
Google ni guarda permisos**: lee la cookie de sesión de la suite y le pregunta
al portal, en cada petición, quién es y qué puede hacer.

Vive en el repositorio del portal (`ampa-portal/cliente`) porque cambia con él:
son las dos mitades del mismo contrato. El porqué del diseño, en el
[README del portal](../README.md).

---

## Qué pone en la aplicación

| | |
|---|---|
| `PortalAuthenticator` | El autenticador del cortafuegos. Cookie → pregunta al portal → usuario con sus roles. **401** sin sesión (y borra la cookie si no valía), **403** si la sesión vale pero no tiene ningún rol aquí, **503** si el portal no contesta. |
| `GET /api/me` | `{ name, email, …lo que añada la aplicación }`. Lo pide el front al abrirse. Lanza el evento `ApplicationOpened`. |
| `POST /api/auth/salir` | Borra la cookie: sale de toda la suite. |
| `PortalUser` | El usuario de Symfony: `getUserIdentifier()` es el correo, `getName()`, `getPortalRoles()`, `getNotificationEmails()`. |
| `SuiteRecipients` | A quién avisar: `emailsWithRole('admin')` da los correos de quienes tienen ese rol aquí, cada uno en el que eligió (el de la cuenta, el personal o los dos). |
| `SuiteMembers` | Quién hay aquí: `all()` da las personas activas con algún rol en esta aplicación (`Member`: correo de la cuenta, nombre, roles y, desde la 0.1.2, `getNotificationEmails()`, a dónde avisarle), por nombre; `has($correo)` dice si alguien es de aquí. Desde la 0.1.1. |
| `CrossSiteRequestGuard` | Rechaza cualquier petición que cambie algo y venga de otra web (cabecera `Sec-Fetch-Site`). Segunda barrera contra CSRF además de `SameSite=Lax`. |
| `JsonAccessDeniedHandler` | Los 403 de `access_control` en JSON (`{"error"}`), no como página de Symfony. |
| `FakePortal` | El portal en los tests de la aplicación, sin red. |

Y dos **puntos de extensión**, que la aplicación sustituye en su
`services.yaml` si los necesita:

- **`ApplicationRoles`**: de roles del portal a roles de Symfony. Por defecto,
  `usuario` → `ROLE_USUARIO`, `admin` → `ROLE_ADMIN`. **Sin ningún rol, no
  entra** (403).
- **`MeExtension`**: campos que se añaden a `GET /api/me`. Por defecto, ninguno.

---

## Instalarlo en una aplicación

### 1. `composer.json`

Un repositorio `package` que apunta al zip de la release. **El número y el
sha1 se copian de las notas de la release** (los pone la Action):

```json
"repositories": [
    {
        "type": "package",
        "package": {
            "name": "ampa/portal-cliente",
            "version": "0.1.0",
            "type": "symfony-bundle",
            "dist": {
                "url": "https://github.com/ampa-sainz-vicuna/ampa-portal/releases/download/v0.1.0/ampa-portal-cliente-0.1.0.zip",
                "type": "zip",
                "shasum": "<sha1 de la release>"
            },
            "autoload": { "psr-4": { "Ampa\\PortalCliente\\": "src/" } },
            "require": {
                "php": ">=8.2",
                "symfony/framework-bundle": "^7.4",
                "symfony/http-client": "^7.4",
                "symfony/security-bundle": "^7.4"
            }
        }
    }
],
"require": {
    "ampa/portal-cliente": "0.1.0"
}
```

Por qué así: un repositorio `package` **no lee el `composer.json` del zip**, así
que el `autoload` y el `require` hay que repetirlos aquí (son esas líneas y no
cambian). A cambio, se descarga de la release sin credenciales ni `git` (lo
mismo que `@ampa/ui`), y con `shasum` Composer **se niega a instalar** un zip
que no sea exactamente el publicado (probado).

Para subir de versión: cambiar las tres cosas (`version`, la URL y el
`shasum`) y el `require`, y `composer update ampa/portal-cliente`.

### 2. `config/bundles.php`

```php
Ampa\PortalCliente\AmpaPortalClienteBundle::class => ['all' => true],
```

### 3. `config/packages/ampa_portal_cliente.yaml`

```yaml
ampa_portal_cliente:
    aplicacion: listados                                 # su código en el catálogo del portal
    portal_url: '%env(PORTAL_URL)%'                      # el portal visto desde ESTE servidor
    cookie_domain: '%env(SESSION_COOKIE_DOMAIN)%'        # el mismo que el portal
    cookie_secure: '%env(bool:SESSION_COOKIE_SECURE)%'
```

Y en `.env`, los valores de desarrollo:

```dotenv
# El portal levantado en local (ampa-portal, docker compose up). Desde un
# contenedor, "localhost" es el propio contenedor: host.docker.internal es Windows.
PORTAL_URL=http://host.docker.internal:8083
SESSION_COOKIE_DOMAIN=
SESSION_COOKIE_SECURE=0
```

En producción: `PORTAL_URL` = la URL `*.run.app` del portal,
`SESSION_COOKIE_DOMAIN=.ampasainzvicuna.com`, `SESSION_COOKIE_SECURE=1`.
Ninguna es secreta.

### 4. `config/routes/ampa_portal_cliente.yaml`

```yaml
ampa_portal_cliente:
    resource: '@AmpaPortalClienteBundle/config/routes.php'
```

Y **borrar** los `AuthController` y `SessionController` propios: `/api/me` pasa
a ser del cliente, y `/api/auth/google` solo existe en el portal.

### 5. `config/packages/security.yaml`

```yaml
security:
    providers:
        # Symfony exige uno; el usuario lo construye el autenticador.
        portal:
            id: Ampa\PortalCliente\Security\PortalUserProvider

    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false

        api:
            pattern: ^/api
            stateless: true
            provider: portal
            custom_authenticators:
                - Ampa\PortalCliente\Security\PortalAuthenticator
            entry_point: Ampa\PortalCliente\Security\PortalAuthenticator
            access_denied_handler: Ampa\PortalCliente\Security\JsonAccessDeniedHandler

    access_control:
        - { path: ^/api/auth/salir$, roles: PUBLIC_ACCESS }
        # …las reglas de la aplicación (^/api/admin → ROLE_ADMIN, etc.)
        - { path: ^/api, roles: IS_AUTHENTICATED }
```

Fuera: `lexik/jwt-authentication-bundle`, su configuración y sus secretos
(`JWT_KEY` o las claves RSA), `GOOGLE_CLIENT_ID`, `GOOGLE_HOSTED_DOMAIN` y
cualquier lista de correos permitidos. `firebase/php-jwt` también, si solo lo
usaba el verificador de Google.

### 6. Roles propios y `/api/me` (solo si hace falta)

```yaml
# services.yaml
Ampa\PortalCliente\Security\ApplicationRoles: '@App\…\Security\FichajesRoles'
Ampa\PortalCliente\Http\MeExtension: '@App\…\Http\FichajesMe'
```

```php
final readonly class FichajesRoles implements ApplicationRoles
{
    public function rolesFor(PortalAccess $access): array
    {
        $roles = [];
        // "empleado" en el portal y además contrato en vigor aquí.
        if ($access->hasRole('empleado') && $this->hasActiveContract($access->getEmail())) {
            $roles[] = 'ROLE_EMPLOYEE';
        }
        if ($access->hasRole('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }
}
```

### 7. Engancharse a "abrir la aplicación" (solo si hace falta)

```php
#[AsEventListener]
final readonly class RunScheduledWorkOnOpen
{
    public function __invoke(ApplicationOpened $event): void { ($this->scheduledWork)(); }
}
```

Se lanza **en cada `GET /api/me`**, es decir, cada vez que alguien carga la
página, no una vez por sesión. Lo que se enganche tiene que ser barato de
repetir, y un fallo no debe impedir abrir la aplicación.

---

## Tests de la aplicación

Sin portal de verdad: en el `when@test` de `services.yaml`,

```yaml
when@test:
    services:
        Ampa\PortalCliente\Portal\Portal:
            class: Ampa\PortalCliente\Testing\FakePortal
```

y en cada test, "entrar" es poner la cookie con lo que contestaría el portal:

```php
$client->getCookieJar()->set(new Cookie(
    PortalSession::COOKIE,
    FakePortal::session('admin@ampasainzvicuna.com', 'Admin', ['usuario']),
));
```

Los avisos se preparan con `FakePortal::recipientsFor('admin', [new Recipient(…)])`, quién
hay en la aplicación con `FakePortal::membersAre([new Member(…)])`,
y se limpian con `FakePortal::reset()` en el `setUp()`.

---

## Desarrollo

Los tests del cliente usan una aplicación mínima (`tests/App/TestKernel.php`)
montada exactamente como dicen los pasos de arriba: si pasan, las
instrucciones funcionan.

```bash
docker compose exec -w /var/www/html/cliente php composer install
docker compose exec -w /var/www/html/cliente php vendor/bin/phpunit
```
