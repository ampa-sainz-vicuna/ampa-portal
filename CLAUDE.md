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
- **Despliegue preparado** (estrenado ese mismo día, ver abajo): `Dockerfile` (construido y probado
  en local contra la base de desarrollo: migra, sirve el front, `/api/acceso`
  en ~25 ms), `deploy/preparar.sh`, `deploy/desplegar.sh`,
  `deploy/dar-permisos.sh` (un *job* de Cloud Run: la contraseña de la base
  no sale de Google; probado en producción). Pasos
  en el README, "Desplegar".
- En la base de desarrollo quedan dos personas de prueba (`admin.prueba@example.com`
  con todo y `vocal.prueba@example.com`).

**Publicado y desplegado (24/09/2026, a petición del usuario: "haz tú commit,
despliegues y demás")**

- `@ampa/ui` **0.2.0** publicada (release en GitHub); `web/` la instala desde
  ahí.
- Cliente **v0.1.0** publicado: la Action pasó los tests de `api/` (contra
  PostgreSQL) y `cliente/` y colgó el zip; sha1
  `795fd875e67e607bad626700877ac5e96cbdf261`.
- Base **`suite`** en Neon (proyecto `ampa`, usuario `suite`, la creó el
  usuario). Secretos `portal-jwt-key` y `portal-database-url`.
- Servicio **`ampa-portal`** en Cloud Run, proyecto `ampa-fichajes-509408`
  (el de toda la suite), `europe-west1`. Migraciones aplicadas en Neon al
  arrancar. Dirección fija, la que usarán los servidores de las aplicaciones
  (`PORTAL_URL`): **`https://ampa-portal-273203000301.europe-west1.run.app`**.
- **`portal.ampasainzvicuna.com`** asociado al servicio (domain mapping). El
  CNAME `portal` → `ghs.googlehosted.com` en CDmon lo pone el usuario; la
  raíz del dominio es la web estática de Firebase y no se toca.
- `gcloud` del portal usa el volumen de sesión de listados
  (`ampa-listados_gcloud_config`, ver `docker-compose.yml`).
- Secret Manager: **11 versiones activas** el 25/09/2026 (6 gratis por cuenta
  de facturación, 0,06 $ al mes cada una de más). Fichajes y listados ya no
  usan sus claves de firma; destruirlas (`jwt-private-key`, `jwt-public-key`,
  `jwt-passphrase` y `listados-jwt-key`) lo deja en 7. Instrucciones en el
  `docs/despliegue.md` de cada una, *Pasar al portal*.

**Tareas (25/09/2026, desde la sesión de `ampa-tareas`; publicado y desplegado con permiso del usuario)**

- `tareas` en `suite.yaml` (roles `miembro` y `admin`), `URL_TAREAS` en
  `api/.env` y `deploy/desplegar.sh`, tarjeta en `web/src/home/Applications.tsx`.
- **Ruta nueva `GET /api/personas?aplicacion=…`** (`MembersController`, abierta
  en el cortafuegos como `/api/avisos`): quién tiene algún rol en esa
  aplicación, con el correo de la **cuenta**, nombre y roles. La pidió tareas
  para elegir responsable (`/api/avisos` da correos de aviso, no identidades).
- **Cliente 0.1.1**: `Member`, `Portal::members()`, `SuiteMembers`,
  `FakePortal::membersAre()`, README. Solo añade: ninguna aplicación tiene que
  cambiar nada (por eso tercera cifra).
- 66 tests de PHP y 21 del cliente en verde.
- **Publicado y desplegado el 25/09/2026**: commit `c1bb536`, release `v0.1.1`
  (sha1 `0335b8884b1f3f1e3a96aa5584739af7582bf4cb`), revisión `ampa-portal-00003-lm6`.

**Pendiente, en este orden**

1. ~~Certificado~~ de `portal.ampasainzvicuna.com`: **funciona** desde el
   24/09/2026 (tardó ~50 minutos tras el CNAME). Queda que el usuario
   compruebe que entra con Google; si el botón da error de origen, falta
   `https://portal.ampasainzvicuna.com` (y `http://localhost:5176`) en los
   orígenes del cliente de OAuth.
2. ~~El primer administrador~~ **Hecho** con `deploy/dar-permisos.sh`:
   **Admin** (`admin@`: portal·admin, fichajes·admin, listados·usuario;
   segundo correo el personal del usuario, avisos a los dos) y **Alberto**
   (`info@`: listados·usuario, fichajes·empleado).
3. ~~Adoptarlo en las tres aplicaciones~~ **Hecho en el código.**
   Facturación fue la primera (25/09/2026) y está desplegada. **Listados y
   fichajes, el 25/09/2026 por la tarde** (Claude, "hazlo tú"), con tests en
   verde y probados contra este portal en local; **desplegados el 25/09/2026**
   (`docs/despliegue.md` de cada una, *Pasar al portal*). El usuario comprobó
   ese día que **la sesión viaja entre aplicaciones**; queda destruir los
   secretos viejos (arriba). Fichajes se desplegó con
   la sesión de gcloud de listados (`docker run -v ampa-listados_gcloud_config:…`):
   la de su propio volumen había caducado. En listados,
   `APP_ALLOWED_EMAILS` desapareció. En fichajes, **decidido con el usuario:
   manda el portal**: fuera el agregado `Administrator` y su pantalla (la
   tabla se queda en la base sin leerse); `fichajes:admin` da la
   administración y los avisos a la junta salen de `/api/avisos`;
   `ROLE_EMPLOYEE` = `fichajes:empleado` + contrato en vigor
   (`FichajesRoles`); `RunScheduledWork` cuelga de `ApplicationOpened`.
   Consecuencia para quien administra: dar de alta a un empleado son **dos
   pasos**, el permiso aquí y el alta con contrato en fichajes.
4. **Cuentas que no son del Workspace** (pedido por el usuario el 25/09/2026,
   "importante"): dar entrada a gente sin pagarle una cuenta del Workspace,
   que hoy es una prueba de Business Starter y **pasa a cobrar por usuario
   hacia el 29/09/2026** (Google for Nonprofits sigue sin aprobarse).

   **Lo que NO sirve: los alias.** Un alias de Workspace (hasta 30 por
   usuario, gratis) solo recibe correo en el buzón de otra cuenta; **no es
   una cuenta de Google y no se puede entrar con él** (ayuda de Google,
   comprobado el 25/09/2026). Un grupo (`junta@`) tampoco.

   **Camino recomendado: Cloud Identity Free.** Cuentas
   `nombre@ampasainzvicuna.com` que **son** cuentas de Google (se entra con
   ellas en el portal) pero **sin Gmail ni Drive y sin licencia de pago**:
   50 gratis por defecto, y se añade al Workspace existente desde la
   consola de administración. Como son de la organización, **el portal no
   cambia nada** (OAuth sigue en *Interno* y el `hd` se cumple). No tienen
   buzón, así que los avisos van al **segundo correo** de su ficha en el
   portal, con "a dónde van los avisos" en *segundo*: para esto se pensó.
   Además, las cuentas son del AMPA: cuando cambia la junta, se le cambia la
   contraseña a la cuenta del cargo, no se pierde nada. **Cuidado**: con el
   Workspace y Cloud Identity a la vez, hay que **desactivar la asignación
   automática de licencias del Workspace** al añadir Cloud Identity (casilla
   *Switch off Auto-Assign*); si no, cada usuario nuevo se lleva una
   licencia de pago. Comprobar en la ficha de cada usuario nuevo que solo
   tiene *Cloud Identity Free*.

   **Camino alternativo, solo si hace falta alguien sin cuenta del dominio**:
   cuentas de Google personales. Hoy solo entran cuentas
   `@ampasainzvicuna.com`, por **tres** cerrojos, y habría que abrir los
   tres:
   - El cliente OAuth está en **Interno** (solo la organización). Pasa a
     **Externo** y a **En producción**. Con solo `openid`, `email` y
     `profile` (lo único que pide el portal) Google no exige verificar la
     aplicación; sin logo en la pantalla de consentimiento, tampoco la marca.
     En *Prueba* solo entran los usuarios de prueba que se apunten a mano.
   - `GOOGLE_HOSTED_DOMAIN=ampasainzvicuna.com` en `api/.env` (el servidor
     rechaza el token si su `hd` no es ese): en producción, vacío, con
     `GOOGLE_HOSTED_DOMAIN=` en el `ENV_VARS` de `deploy/desplegar.sh`
     (comprobar al hacerlo que la revisión nueva lo lleva vacío y no sin
     definir: sin definir, Symfony usaría el de `api/.env`).
   - `VITE_GOOGLE_HOSTED_DOMAIN` en `web/.env` (el botón de Google solo
     ofrece cuentas de ese dominio): vacío. Va dentro de la imagen, así que
     hace falta redesplegar, no basta con `services update`.
   Con esto, la barrera pasaría a ser **solo el permiso** de cada persona. Cada persona entraría
   con su Gmail o, si no tiene, con una **cuenta de Google creada con el
   correo que ya use** (<https://accounts.google.com/signup>, «Usar mi
   dirección de correo electrónico actual»): es gratis y no ocupa licencia.
   Antes de su primera entrada, alguien con permiso de administración le da
   de alta en *Permisos* con **ese mismo correo** (la ficha se busca por el
   correo de la cuenta de Google). Fichajes no cambia: exige además el alta
   con contrato.
5. **Saltar entre aplicaciones desde la barra** (pedido por el usuario el
   25/09/2026). `/api/acceso` añade `applications` (código, nombre, url; lo
   mismo que ya calcula `SessionPresenter`), el cliente lo pasa a `/api/me`
   (0.1.2: solo añade) y `@ampa/ui` lo pinta en `AppShell`. Después, cada
   aplicación sube de versión y se redespliega.
6. Tareas: su repositorio en GitHub y su despliegue (ver su `CLAUDE.md`).

---

## Trampas conocidas

Las del [README](README.md#trampas-conocidas): `UserId::__toString()` para
Doctrine, `when@test` sin autowiring, `status()` final en PHPUnit 13, HS256 con
clave de 32 bytes o más, y la lentitud del entorno de desarrollo sobre el
disco de Windows (1-2 s por petición al portal). Las heredadas de la suite, en
el [`CLAUDE.md` de fichajes](../ampa-fichajes/CLAUDE.md).
