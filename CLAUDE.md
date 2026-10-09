# AMPA Portal — contexto para Claude

El **portal del AMPA** y **back común** de la suite: el único que habla con
Google, el único dueño de los permisos (persona × aplicación × rol) y quien da
la sesión de toda la suite en una cookie de `.ampasainzvicuna.com`. Dentro
lleva también el **cliente** (`cliente/`, el bundle `ampa/portal-cliente`) que
instala cada aplicación para preguntarle al portal en cada petición.

Detalle en [README.md](README.md) y [cliente/README.md](cliente/README.md);
diseño y porqué, en la
[hoja de ruta de fichajes, sección 4a](../ampa-fichajes/docs/hoja-de-ruta.md);
lo hecho, en [docs/historial.md](docs/historial.md).

Repositorio **público** (las aplicaciones descargan el cliente de la release
sin credenciales): nada de correos reales en código, migraciones, tests ni
`.md`.

## Lo propio de este repo

Aquí vive el **contrato** con las aplicaciones (`/api/acceso`, `/api/avisos`,
`/api/personas`, `/api/calendario`, `/api/latido`, el nombre de la cookie, las
interfaces públicas del cliente) y con el front (`/api/me` de cada aplicación,
`/api/auth/salir`). Cómo subir versión, en el global. El cliente se publica
con una etiqueta `vX.Y.Z` (Action `.github/workflows/publicar.yml`, que cuelga
el zip; cada aplicación lo instala con su sha1): README, *Publicar una versión
del cliente*.

## Stack

PHP 8.4.25, Symfony 7.4, Doctrine ORM 3.7 / DBAL 4.4, PostgreSQL 16,
firebase/php-jwt 7.2 (verificar Google y firmar la sesión, HS256), PHPUnit
13.3. Sin lexik. Front: React 19, MUI 9, `@ampa/ui` 0.2.13, Vitest. Puertos
(5176 / 8083 / 5435) en el global; base `suite`, tests en `suite_test`.

```bash
docker compose up -d
docker compose exec php vendor/bin/phpunit --testsuite unit
docker compose exec php vendor/bin/phpunit --testsuite integration
docker compose exec -w /var/www/html/cliente php vendor/bin/phpunit
docker compose run --rm node npm test        # y npm run lint / build, en web/
docker compose exec php bin/console app:permisos:dar <correo> <app:rol>... --nombre="…"
docker compose exec php bin/console app:ayuda:cargar <faq.json>
docker compose run --rm gcloud bash deploy/desplegar.sh
```

## Estructura

```
api/src/Portal/
  Domain/User/       User (agregado), Grants (JSON aplicación → roles), EmailAddress, UserId
  Domain/Suite/      Application (con su latido), ApplicationCatalog (de config/packages/suite.yaml)
  Domain/Calendar/   SchoolYear (clases y días sin clase), CalendarPeriod, DayKind
  Domain/Help/       Faq (una sola fila), FaqEntry (quién ve cada pregunta)
  Application/       User/, Calendar/, Help/ (LoadFaq), Heartbeat/ (RunHeartbeat y sus puertos)
  Infrastructure/    Security/ (Google, SessionTokens, BearerCaller, CrossSiteRequestGuard…),
                     Http/, Persistence/ (Doctrine, mapeo XML), Heartbeat/ y Google/ (adaptadores)
cliente/src/         PortalAuthenticator, HttpPortal / FakePortal, PortalUser, Suite*,
                     controladores de /api/me, salir y latido, ApplicationRoles
web/src/             home/ (tarjetas), permissions/, calendar/, help/ (las tres, solo admin, con lazy)
deploy/              desplegar, preparar, dar-permisos, programar (latido), alertas,
                     copias/ (el job de las copias de seguridad)
```

## Cómo funciona hoy (lo que hay que saber para tocarlo)

- **Producción**: Cloud Run `ampa-portal` en `portal.ampasainzvicuna.com`.
  Los servidores de las aplicaciones usan la dirección fija
  `https://ampa-portal-273203000301.europe-west1.run.app` (`PORTAL_URL`).
  Secretos `portal-jwt-key` y `portal-database-url`; base `suite` en Neon.
  `--timeout` 300 s (por el latido). `--min-instances=1` **descartado**.
- **Desplegar**: `deploy/desplegar.sh` crea la revisión **sin tráfico**, lanza
  el job **`portal-migraciones`** con esa imagen y solo si sale bien pasa el
  tráfico. El contenedor no migra al arrancar.
- **Entrar con Google, en modo redirección**: Google vuelve con un POST a
  `/api/auth/google/vuelta` (compara la cookie `g_csrf_token` con el campo,
  comprueba el token y lleva con 303 a `/?entrada=ok|sin-acceso|no-valida|caducada`).
  Tiene que estar en los **URI de redirección autorizados** del cliente OAuth
  (si no, `redirect_uri_mismatch`). OAuth *Interno* y `GOOGLE_HOSTED_DOMAIN`:
  solo `@ampasainzvicuna.com`.
- **Quién puede llamar a qué**: `/api/acceso` solo con la sesión de una
  persona. `/api/personas`, `/api/avisos` y `/api/calendario` aceptan además el
  **token de identidad de la cuenta de servicio de la suite**
  (`SUITE_TOKEN_AUDIENCE`, `SUITE_SERVICE_ACCOUNTS`; vacías en desarrollo: no
  se acepta ninguno). El portal no distingue qué aplicación llama.
- **Última entrada** (`lastSeenAt`): como mucho una vez por hora, en `/api/me`
  y `/api/acceso`; si falla, se anota y sigue. «Nunca ha entrado» en
  *Permisos* delata un correo mal escrito.
- **Cuentas sin licencia**: quien no es de la junta entra con **Cloud Identity
  Free** (sin buzón), con su correo personal como **segundo correo** y los
  avisos a *segundo*. Al crearlas, comprobar que no llevan licencia de
  Workspace. Abrir la entrada a cuentas personales exige tres cerrojos: en el
  historial, punto 4.
- **Latido** (`suite-latido` en Cloud Scheduler, 4:00 → `POST /api/latido`):
  mira Neon (avisa ≥ 80 % de 100 CU-horas), lanza el job `ampa-copias` y
  despierta a las aplicaciones con `latido: true` en `suite.yaml` (fichajes y
  tareas). Siempre 200; los fallos, `[error]` en el registro.
- **Copias**: job `ampa-copias` (`deploy/copias/`): `pg_dump` de cada base a
  una unidad compartida de Drive, 30 por base (las 8). Se comprueban con
  `probar-copias.sh` (`ampa-claude`) **cada mes**.
- **Alertas** (`deploy/alertas.sh`): `[error]`, 5xx, fallos de copias y de
  Cloud Scheduler, a admin@, un correo por hora como mucho. 404 y 405 solo
  `warning` (robots que buscan `/.env`).
- **Ayuda de la suite**: `GET /api/ayuda` (sesión, filtrado por roles, CORS a
  las `URL_…` del catálogo y a `HELP_ALLOWED_ORIGINS`). Contenido en
  `ampa-manuales/ayuda/faq.json` (privado), cargado en la pestaña *Ayuda*. El
  enlace a NotebookLM va en el fichero, no aquí (repositorio público).
- **Junta** (`board_members`): cargos con un titular activo (presidencia,
  vicepresidencia, secretaría, tesorería) o varios (vocal). Pestaña solo admin
  (`/api/admin/junta`); `GET /api/junta`, cualquier sesión, solo nombre,
  correo y cargo. **DNI, dirección y teléfono nunca a logs ni errores.** Sin
  «reactivar»: alta nueva.
- **Calendario escolar**: el curso 2026/27 está cargado; lo usa facturación.
  `GET /api/calendario/publico` (`PublicCalendarController`, firewall `public`
  con patrón exacto) **no pide sesión**: da el curso de hoy (hora de
  Europe/Madrid, Cloud Run va en UTC) y los posteriores; `Cache-Control`
  1 h. Lo lee `ampa-web` al construirse. No es contrato del cliente.

### Dar de alta una aplicación nueva

Como con Proveedores (`crm`) y Documentos: su entrada en `suite.yaml`
(nombre visible y roles; `latido: true` si hace algo programado); `URL_X` en
`api/.env` (su Vite) y en `deploy/desplegar.sh` (su dominio); la tarjeta en
`web/src/home/Applications.tsx`; su puerto en `HELP_ALLOWED_ORIGINS` de
`api/.env`; `BASE_X=x-database-url` en `deploy/copias/preparar.sh` (se salta
hasta que exista el secreto; después, volver a lanzar ese `preparar.sh`); el
recuento de `HelpApiTest`; el icono en `@ampa/ui`; y los roles de las dos
personas de prueba en la base local con `app:permisos:dar`.

## Estado

**En producción**: revisión **`ampa-portal-00025-qjm`** (commit `5ac38f1`,
`@ampa/ui` 0.2.13; `desplegar.sh` pone la etiqueta `commit`); cliente
**v0.1.5**, el que usan todas las aplicaciones. Copias de **8 bases**.

**Pendiente**

- **El usuario** comprueba en pantalla que los desplegables (0.2.13) abren a
  la primera.
- **Claude (otra sesión)**: en el SQL Editor de Neon, **solo consultar**, qué rol
  es dueño de cada base (`SELECT datname, pg_get_userbyid(datdba) FROM
  pg_database`), por si otra tiene un dueño ajeno (la de familias salió con
  `listados`).
- **El usuario** prueba la pestaña *Junta* y carga los cargos reales.
- **Claude**: exponer los cargos en `ampa/portal-cliente` (adición, tercera
  cifra) para que fichajes use la secretaría y no `REGISTRO_FIRMA_SECRETARIA`.
- **El usuario** lanza `probar-copias.sh` cada mes.
- **El usuario** lee las preguntas de la ayuda cuando pueda (se corrigen en
  `faq.json` y se vuelve a cargar).
- **Base y usuario propios de fichajes** (la única que entra con el dueño de
  Neon): preparado en fichajes; espera al usuario, "no corre prisa".
- **Claude (otra sesión)**: el repo es público y `api/tests/Portal/Api/CalendarApiTest.php`
  (quizá otros tests) lleva direcciones reales de `@ampasainzvicuna.com`;
  sustituirlas por `@example.com`.

**No hacer sin que lo pida el usuario**: el resumen diario de toda la suite;
registro de cambios de permisos y repaso anual; avisar en la ficha si una
cuenta sin buzón deja los avisos en *principal*; que el diálogo de la ficha no
se cierre al pulsar fuera; un buscador en *Permisos*.

Ideas sin repo y datos comunes (fase 3): en el historial.

## Trampas conocidas

- Las del [README](README.md#trampas-conocidas): `UserId::__toString()` para
  Doctrine, `when@test` sin autowiring, `status()` final en PHPUnit 13, HS256
  con clave de 32 bytes o más.
- **Neon**: `compute_time_seconds` es la CPU usada, no lo que se cobra; el plan
  gratuito no da `consumption_history`. El latido calcula **el máximo posible**
  (tiempo encendida × la CU máxima). La cifra se ve en la respuesta de
  `POST /api/latido`, no en el registro. **La máquina está fija en 0,25 CU**
  (mín. y máx.); una máquina nueva hay que fijarla igual. Detalle en el historial.
- `board_members`: el índice único parcial, la FK y los CHECK solo están en la
  migración; un `migrations:diff` propone borrarlos: no aceptarlo.
- `HeartbeatApiTest` y `HelpApiTest` leen el `suite.yaml` de verdad: al dar de
  alta o cambiar una aplicación, cambian sus recuentos.
- Variables vacías en `deploy/desplegar.sh` (`HELP_ALLOWED_ORIGINS=`, y
  `GOOGLE_HOSTED_DOMAIN=` si algún día se abre a cuentas personales): tienen
  que ir **vacías y definidas**; sin definir, Symfony usa las de `api/.env`.
- Los `VITE_…` van dentro de la imagen: cambiarlos exige redesplegar, no
  basta con `services update`.
- Si otra sesión trabaja en el repo, desplegar desde un worktree limpio; y los
  tests de integración de dos sesiones (o dos `comprobar.sh`) a la vez chocan
  en `suite_test` (p. ej. `BoardApiTest` da 401): si fallan raro, repetir solo.
- Base nueva en Neon: crearla con dueño **`neondb_owner`**, no con el rol de otra
  aplicación.
