# AMPA Portal — contexto para Claude

El **portal del AMPA** y **back común** de la suite: el único que habla con
Google, el único dueño de los permisos (persona × aplicación × rol) y quien da
la sesión de toda la suite en una cookie de `.ampasainzvicuna.com`. Dentro
lleva también el **cliente** (`cliente/`, el bundle `ampa/portal-cliente`) que
instala cada aplicación para preguntarle al portal en cada petición.

Hermanas: [`ampa-fichajes`](../ampa-fichajes/CLAUDE.md),
[`ampa-listados`](../ampa-listados/CLAUDE.md),
[`ampa-facturacion`](../ampa-facturacion/CLAUDE.md) y el front común
[`ampa-ui`](../ampa-ui/CLAUDE.md). El diseño y por qué, decidido con el usuario
el 24/09/2026, en la [hoja de ruta, sección 4a](../ampa-fichajes/docs/hoja-de-ruta.md);
el detalle de cada pieza, en [README.md](README.md) y
[cliente/README.md](cliente/README.md). Este fichero es el resumen para
arrancar.

Repositorio **público**: `https://github.com/ampa-sainz-vicuna/ampa-portal`
(las aplicaciones descargan el cliente de la release sin credenciales). Nada
de correos reales en el código, en las migraciones ni en los tests.

---

## Cómo trabajar con el usuario

El mismo que en el resto de la suite; las reglas, con su motivo, en el
[`CLAUDE.md` de fichajes](../ampa-fichajes/CLAUDE.md). En resumen: en español;
por defecto dictar ficheros enteros marcados NUEVO o REEMPLAZAR; si dice "hazlo
tú", escribirlos, pasar los tests y terminar con el listado de ficheros; no
hacer commit salvo que lo pida; verificar antes de afirmar; explicar el
porqué; getters siempre (`private` + `getX()`); Material Design (MUI) con los
colores del AMPA y estilos con `sx`, sin Tailwind; Google Cloud muy mascado.

**Aquí, además:** el contrato con las aplicaciones (`/api/acceso`,
`/api/avisos`, el nombre de la cookie, las interfaces públicas del cliente) y
con el front (`/api/me` de cada aplicación, `/api/auth/salir`) lo usan todas.
Un cambio que obliga a tocarlas sube la **segunda cifra** de la versión y las
notas de la release dicen qué cambiar en cada una.

---

## Stack (verificado el 24/09/2026)

PHP 8.4.25, **Symfony 7.4**, Doctrine ORM 3.7 / **DBAL 4.4**, PostgreSQL 16,
firebase/php-jwt 7.2 (verificar Google y firmar la sesión, HS256), PHPUnit 13.3
con `#[Test]`. Sin lexik. Todo en Docker; **no hay PHP en Windows**.

| | |
|---|---|
| Web (Vite) | **5176** (React 19, MUI 9, `@ampa/ui` 0.2.0, Vitest) |
| API (nginx) | **8083** |
| PostgreSQL | **5435** (base `suite`, tests en `suite_test`) |

```bash
docker compose up -d
docker compose exec php vendor/bin/phpunit --testsuite unit
docker compose exec php vendor/bin/phpunit --testsuite integration
docker compose exec -w /var/www/html/cliente php vendor/bin/phpunit
docker compose exec php bin/console app:permisos:dar <correo> <app:rol>... --nombre="…"
```

(Desde Git Bash, `MSYS_NO_PATHCONV=1` delante de los que llevan `-w`.)

## Estructura

```
api/src/Portal/
  Domain/User/       User (agregado: correo, nombre, activa, Grants, segundo correo,
                     NotificationTarget), Grants (JSON aplicación → roles), EmailAddress, UserId
  Domain/Suite/      Application, ApplicationCatalog (de config/packages/suite.yaml)
  Application/User/  RegisterUser, UpdateUser, ChangeOwnContact, GrantAccess (comando)
  Infrastructure/
    Security/        Google, SessionTokens (HS256), SessionCookie, el autenticador de la
                     cookie, BearerUser (rutas que llaman las aplicaciones), CrossSiteRequestGuard
    Http/            controladores y presentadores
    Persistence/     Doctrine con mapeo XML y un tipo por value object
cliente/src/         PortalAuthenticator, HttpPortal / FakePortal, PortalUser, SuiteRecipients,
                     MeController, SignOutController, ApplicationRoles y MeExtension
```

---

## Estado

**Hecho (24/09/2026, "hazlo tú")**

- **Servicio** (`api/`): entrar con Google → cookie; `/api/me`;
  `/api/me/contacto`; pantalla de permisos (`/api/admin/users`,
  `/api/admin/applications`); `/api/acceso` y `/api/avisos` para las
  aplicaciones; comando `app:permisos:dar`. **Segundo correo y a dónde van los
  avisos** (`primary`/`secondary`/`both`), pedido por el usuario a mitad del
  trabajo: la junta entra con la cuenta de Workspace y quiere los avisos en su
  correo personal. **25 tests unitarios y 39 de integración en verde.**
- **Cliente** (`cliente/`): autenticador (401/403/503), `/api/me` con el evento
  `ApplicationOpened` (sustituye a "al entrar"; lo usará el "cron" de
  fichajes), salir, avisos, guarda contra CSRF, 403 en JSON y `FakePortal`
  para los tests de las aplicaciones. **19 tests en verde**, con una aplicación
  mínima montada exactamente como dice su README.
- Probado el viaje real: el cliente contra el portal levantado, por HTTP.
- Probado el **empaquetado**: el zip se instala por un repositorio `package` de
  Composer, el bundle encuentra su `config/`, y con un sha1 que no coincide
  Composer se niega a instalarlo.
- **Action** de publicación escrita (`.github/workflows/publicar.yml`), **sin
  estrenar**: todavía no se ha subido ninguna etiqueta.
- **Front** (`web/`, mismo día, "hazlo tú" y el usuario en una reunión):
  entrar con Google, volver a la aplicación de origen (`?volver=`, solo a SUS
  aplicaciones y solo sola si se acaba de entrar), tarjetas, "Mis correos",
  pantalla de permisos. **14 tests, lint, tipos y build en verde**; visto en
  el navegador con una cookie de desarrollo (entrar con Google no lo puede
  hacer Claude): tarjetas, permisos, guardar una ficha (comprobado en la base)
  y salir. Usa **`@ampa/ui` 0.2.0, escrita a la vez y sin publicar**, con
  `file:` desde el `.tgz` local.
- **Despliegue preparado, sin estrenar**: `Dockerfile` (construido y probado
  en local contra la base de desarrollo: migra, sirve el front, `/api/acceso`
  en ~25 ms), `deploy/preparar.sh`, `deploy/desplegar.sh`,
  `deploy/dar-permisos-produccion.sh` (probado contra la base local). Pasos
  en el README, "Desplegar".
- En la base de desarrollo quedan dos personas de prueba (`admin.prueba@example.com`
  con todo y `vocal.prueba@example.com`).

**Pendiente, en este orden** (hoja de ruta 4a; detalle en el README, "Desplegar")

1. El usuario revisa y hace commit de `ampa-portal`, `ampa-ui` y la hoja de
   ruta (Claude no hace commit).
2. **Publicar `@ampa/ui` 0.2.0** e instalarla en `web/` desde la release.
3. **Desplegar el portal**: base `suite` en Neon, `preparar.sh`,
   `desplegar.sh`, dominio `portal.ampasainzvicuna.com`, origen en OAuth, el
   primer administrador con `dar-permisos-produccion.sh`. Guía de consola "muy
   mascada" por escribir al hacerlo.
4. Primera etiqueta **v0.1.0** del cliente.
5. **Adoptarlo en listados** → fichajes → facturación. En listados:
   `APP_ALLOWED_EMAILS` → `listados:usuario`. En fichajes: administradores
   activos → `fichajes:admin` con su segundo correo; empleados →
   `fichajes:empleado`; `ROLE_EMPLOYEE` = permiso + contrato en vigor
   (`ApplicationRoles` propio); `RunScheduledWork` colgado de
   `ApplicationOpened`; la tabla `administrators` y su pantalla, por decidir
   (el segundo correo ya vive en el portal).

---

## Trampas conocidas

Las del [README](README.md#trampas-conocidas): `UserId::__toString()` para
Doctrine, `when@test` sin autowiring, `status()` final en PHPUnit 13, HS256 con
clave de 32 bytes o más, y la lentitud del entorno de desarrollo sobre el
disco de Windows (1-2 s por petición al portal). Las heredadas de la suite, en
el [`CLAUDE.md` de fichajes](../ampa-fichajes/CLAUDE.md).
