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
`.md`. En tests, `@example.com` (cuenta de la suite), `.org` (segundo correo)
o `.net`; nunca el dominio real ni gmail. Un grep de `ampasainzvicuna.com` no
ve los regex con `\.` escapado.

## Lo propio de este repo

Aquí vive el **contrato** con las aplicaciones (`/api/acceso`, `/api/avisos`,
`/api/personas`, `/api/calendario`, `/api/latido`, el nombre de la cookie, las
interfaces públicas del cliente) y con el front (`/api/me` de cada aplicación,
`/api/auth/salir`). Cómo subir versión, en el global. El cliente se publica
con una etiqueta `vX.Y.Z` (Action `.github/workflows/publicar.yml`, que cuelga
el zip; cada aplicación lo instala con su sha1): README, *Publicar una versión
del cliente*.

## Stack

PHP 8.4.27 (imagen fijada en el `Dockerfile`), Symfony 7.4, Doctrine ORM 3.7 / DBAL 4.4, PostgreSQL 16,
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
- **Desplegar, siempre a mano** (`deploy/desplegar.sh`, lo lanza el usuario):
  **el push no despliega** (no hay workflow; `publicar.yml` solo cuelga
  etiquetas `v*`). Crea la revisión **sin tráfico**, lanza
  **`portal-migraciones`** y solo si sale bien pasa el tráfico. El contenedor
  no migra al arrancar.
- **Entrar con Google** (redirección): POST a `/api/auth/google/vuelta`; debe
  estar en los **URI de redirección** del cliente OAuth (si no,
  `redirect_uri_mismatch`). OAuth *Interno* y `GOOGLE_HOSTED_DOMAIN`: solo
  `@ampasainzvicuna.com`.
- **Quién puede llamar a qué**: `/api/acceso` solo con la sesión de una
  persona. `/api/personas`, `/api/avisos` y `/api/calendario` aceptan además el
  **token de identidad de la cuenta de servicio de la suite**
  (`SUITE_TOKEN_AUDIENCE`, `SUITE_SERVICE_ACCOUNTS`; vacías en desarrollo: no
  se acepta ninguno). El portal no distingue qué aplicación llama.
- **Última entrada** (`lastSeenAt`, como mucho una vez por hora): «Nunca ha
  entrado» en *Permisos* delata un correo mal escrito.
- **Cuentas sin licencia**: quien no es de la junta entra con **Cloud Identity
  Free** (sin buzón); sus avisos van a su **segundo correo**. Cerrojos: historial, punto 4.
- **Latido** (`suite-latido`, 4:00 → `POST /api/latido`): mira Neon (avisa ≥ 80 %
  de 100 CU-horas), lanza `ampa-copias` y despierta a las aplicaciones con
  `latido: true` (fichajes, tareas, familias, documentos) en la **raíz del
  dominio** aunque la URL lleve ruta. Siempre 200; fallos, `[error]`.
- **Avisos de permisos** (`avisos_permisos: true` en `suite.yaml`; hoy solo
  `documentos`): si cambian los roles de una persona en esa aplicación
  (también al desactivarla), `GrantsChangeAnnouncer` (en RegisterUser,
  UpdateUser y GrantAccess) llama por `GrantsChangeNotifier` a
  `POST {raíz}/api/service/permisos` con ID token y `{"aplicacion": código}`,
  timeout 10 s; nunca lanza (el latido lo cubre). Detalle en el README.
- **Copias**: job `ampa-copias` (`deploy/copias/`): `pg_dump` de las 8 bases
  a una unidad de Drive, 30 por base. `probar-copias.sh` **cada mes**.
- **Alertas** (`deploy/alertas.sh`): `[error]`, 5xx y fallos de copias o de
  Scheduler, a admin@, una vez por hora (404 y 405, solo `warning`).
- **Ayuda**: `GET /api/ayuda` (filtrado por roles). El contenido es
  `ampa-manuales/ayuda/faq.json` (privado); el enlace a NotebookLM va allí.
- **Junta** (`board_members`): `/api/admin/junta` (solo admin); `GET /api/junta`
  da solo nombre, correo y cargo. **DNI, dirección y teléfono nunca a logs.**
- **Calendario**: curso 2026/27; lo usa facturación. `GET /api/calendario/publico`
  **sin sesión** (hora de Madrid, no UTC), caché 1 h; lo lee `ampa-web`.

### Dar de alta una aplicación nueva

Su entrada en `suite.yaml`
(nombre visible y roles; `latido: true` si hace algo programado;
`avisos_permisos: true` si necesita saber al instante los cambios de roles); `URL_X` en
`api/.env` (su Vite) y en `deploy/desplegar.sh` (su dominio); la tarjeta en
`web/src/home/Applications.tsx`; su puerto en `HELP_ALLOWED_ORIGINS` de
`api/.env`; `BASE_X=x-database-url` en `deploy/copias/preparar.sh` (se salta
hasta que exista el secreto; después, volver a lanzar ese `preparar.sh`); el
recuento de `HelpApiTest`; el icono en `@ampa/ui`; y los roles de las dos
personas de prueba en la base local con `app:permisos:dar`.

## Estado

**En producción**: revisión **`ampa-portal-00029-thg`** (21/10/2026, commit
`a78ad1d`; PHP 8.4.27; `@ampa/ui` 0.2.13; `desplegar.sh` pone la etiqueta `commit`);
cliente **v0.1.5**, el que usan todas las aplicaciones (el contrato no
cambió). Copias de **8 bases**. `faq.json` al día en la pestaña *Ayuda*.

**Pendiente**

- **El usuario** prueba en producción los avisos de permisos: cambiar un rol
  de `documentos`; Claude mira después los registros.
- **El usuario** mira en el latido de las 4:00 que «familias» sale «hecho»
  (si 401: `LATIDO_AUDIENCIA`).
- **Claude (otra sesión)**: en Neon, **solo consultar** el dueño de cada base
  (`pg_database`); la de familias salió con `listados`.
- **El usuario** prueba la pestaña *Junta* y carga los cargos reales.
- **Claude**: exponer los cargos en `ampa/portal-cliente` (adición, tercera
  cifra) para que fichajes use la secretaría y no `REGISTRO_FIRMA_SECRETARIA`.
- **El usuario** lanza `probar-copias.sh` cada mes.
- **Base y usuario propios de fichajes** (la única con el dueño de Neon):
  espera al usuario, sin prisa.

**No hacer sin que lo pida el usuario**: resumen diario de la suite; repaso
anual de permisos; avisar en la ficha de cuentas sin buzón con avisos en *principal*; cerrar el
diálogo de la ficha al pulsar fuera; buscador en *Permisos*. Ideas: historial.

## Trampas conocidas

- Las del [README](README.md#trampas-conocidas): `UserId::__toString()` para
  Doctrine, `when@test` sin autowiring, `status()` final en PHPUnit 13, HS256
  con clave de 32 bytes o más.
- **Neon**: `compute_time_seconds` es CPU usada, no lo cobrado; sin
  `consumption_history` en el plan gratuito. El latido calcula **el máximo
  posible**; la cifra sale en la respuesta de `POST /api/latido`. **Máquina
  fija en 0,25 CU** (mín. y máx.); una nueva, igual. Detalle en el historial.
- `board_members`: el índice único parcial, la FK y los CHECK solo están en la
  migración; un `migrations:diff` propone borrarlos: no aceptarlo.
- `HeartbeatApiTest` y `HelpApiTest` leen el `suite.yaml` de verdad: al dar de
  alta o cambiar una aplicación, cambian sus recuentos.
- Variables vacías en `deploy/desplegar.sh` (`HELP_ALLOWED_ORIGINS=`, y
  `GOOGLE_HOSTED_DOMAIN=` si algún día se abre a cuentas personales): tienen
  que ir **vacías y definidas**; sin definir, Symfony usa las de `api/.env`.
- Los `VITE_…` van dentro de la imagen: cambiarlos exige redesplegar, no
  basta con `services update`.
- Con otra sesión en el repo, desplegar desde un worktree limpio; dos tests
  de integración a la vez chocan en `suite_test` (`BoardApiTest` da 401):
  si fallan raro, repetir solo.
- Base nueva en Neon: crearla con dueño **`neondb_owner`**, no con el rol de otra
  aplicación.
- **PHP**: el `Dockerfile` fija `php:8.4.27-apache` (parche de seguridad del
  21/10/2026). En cada parche nuevo de PHP, subir esa línea a mano, o Cloud Build
  monta una 8.4 vieja.
