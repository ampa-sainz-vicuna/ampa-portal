# Historial — lo que salió del CLAUDE.md el 03/10/2026

La historia del portal, punto por punto, tal como estaba en el `CLAUDE.md`
hasta el 03/10/2026. Lo que se añada a partir de ahora va **arriba**, lo más
reciente primero. El resumen para trabajar, en el [`CLAUDE.md`](../CLAUDE.md).

**08/10/2026 — Primera prueba de restauración de las copias**

- **Qué se hizo**: por primera vez se comprobó que las copias del job
  `ampa-copias` (`deploy/copias/`) **se pueden restaurar**. Script nuevo en el
  repo `ampa-claude`: `scripts/probar-copias.sh`. Baja la última copia de cada
  base de la unidad de Drive, la restaura en un PostgreSQL 16 desechable de
  Docker y comprueba que las tablas clave tienen filas y que la copia tiene
  menos de 48 h.
- **Resultado**: las **7 bases bien**.
- **Permisos**: para leer Drive, el script se hace pasar por la cuenta de
  servicio del job (solo lectura de Drive). A la cuenta de gcloud se le dio el
  rol *Creador de tokens de cuenta de servicio* **solo sobre esa cuenta**.
- **Documentado** en `deploy/copias/README.md`, sección «Comprobar que se
  pueden restaurar (cada mes)» (commit `fcadc35`).
- **Pendiente**: lanzarlo cada mes (el usuario).

**08/10/2026 — Neon: máquina fija en 0,25 CU**

- **Qué pasó**: el latido de las 4:00 avisó de «como mucho 92,4 de 100
  CU-horas» (periodo 01/10-01/11). La consola de Neon decía **12,27 reales**.
  El latido calcula el máximo (`active_time_seconds` × `autoscaling_limit_max_cu`)
  y la máquina podía crecer hasta 2 CU, aunque casi siempre trabajaba a 0,25.
  Uso medido con la API (`operations`): unas 6,5 h encendida al día, unos 50
  arranques diarios, casi todo de 6:00 a 21:00.
- **Decisión** (el usuario, con un PATCH a la API de Neon): el endpoint
  `ep-odd-wind-b1idc2g2` del proyecto `small-pond-03723796` queda **fijo en
  0,25 CU (mínimo y máximo)**. Así el máximo del latido ≈ lo real (sin falsas
  alarmas); techo de unas 186 CU-horas aunque nunca se durmiera; con el uso
  actual, unas 50 de 100 al mes. `default_endpoint_settings` del proyecto sigue
  en 0,25-2 (solo afecta a máquinas nuevas, que habría que fijar igual). Si la
  suite va lenta por la base, subir el máximo a 0,5 dobla el consumo.
- **Costes estudiados por si hiciera falta pagar** (precios oficiales del
  08/10/2026, sin IVA): Neon Launch 0,106 $/CU-h sin cuota mínima (con 0,25 CU
  fijo: ~5 $/mes con el uso actual, techo ~20 $; sin tope de gasto duro, solo
  avisos). Precio fijo: Scaleway DB-DEV-S ~11,40 €/mes + disco, Aiven Hobbyist
  12 $, DigitalOcean 15,15 $. **Decisión del usuario: seguir en Neon gratis.**
- Sin cambios de código ni despliegue.

**05/10/2026 — Copias comprobadas a mano y datos comunes de la suite**

- **Copias**: se lanzó a mano el job `ampa-copias` (ejecución
  `ampa-copias-n8qqv`). Las 7 bases (crm, documentos, facturacion, fichajes,
  listados, portal, tareas) subidas a Drive, «todas bien». Con eso queda hecho
  el pendiente de ver la copia de `documentos`, que se quita del `CLAUDE.md`.
  Sin despliegue del portal (sigue la revisión `ampa-portal-00020-5b7`).
- **Datos comunes de la suite**: se habló y decidió el 05/10/2026 (el bloque
  «Datos comunes de la suite (fase 3…)» está en el [`CLAUDE.md`](../CLAUDE.md),
  commit `e097fdb`). Fichajes debe leer el calendario del portal en vez de sus
  festivos propios, y puede haber un catálogo común de extraescolares. Fase 1,
  **hecha** en `ampa-listados`: configurar los grupos desde el listado y desde
  el export de grupos de MiAmpa. Fase 2, **sin empezar**: misma normalización
  y mismo lector de grupos en listados y facturación. Fase 3, sin empezar.

**Movido desde el CLAUDE.md (03/10/2026)**

Recorte de higiene del `CLAUDE.md` (10,7 KB a unos 9 KB). Lo que salió, tal
como estaba:

- *Estado*: «**En producción**: commit `cb2149f`, revisión
  **`ampa-portal-00020-5b7`** (con Documentos en el catálogo, 03/10/2026);
  cliente **v0.1.5** (sha1 `20260e590538aa64ccf8c56584df44c165c2d3c8`), el que
  usan todas las aplicaciones.»
- *Lo propio de este repo*: «Si un cambio obliga a tocarlas, sube la **segunda
  cifra** y las notas de la release dicen qué cambiar en cada una; si solo
  añade, la tercera.» (Está en el `CLAUDE.md` global.)
- *Stack*: la tabla de puertos: Web (Vite) **5176**, API (nginx) **8083**,
  PostgreSQL **5435** (base `suite`, tests en `suite_test`). (Están en el
  global.)
- *Producción*: «(domain mapping; el CNAME en CDmon lo pone el usuario)».
- *Desplegar*: «(arranque en frío más corto)» tras «El contenedor no migra al
  arrancar».
- *Entrar con Google*: «(por qué, en el historial)».
- *Última entrada*: «se guarda como mucho una vez por hora, al entrar, en
  `/api/me` y en `/api/acceso`; si falla, se anota y la petición sigue. En
  *Permisos*, "Nunca ha entrado" delata un correo mal escrito.»
- *Cuentas sin licencia*: «la gente que no es de la junta entra con cuentas de
  **Cloud Identity Free** del dominio (sin buzón): en su ficha va su correo
  personal como **segundo correo** con los avisos a *segundo*. Al crear
  usuarios, comprobar que solo tienen *Cloud Identity Free* (la asignación
  automática de licencias del Workspace, apagada).»
- *Alertas*: «cualquier `[error]`, 5xx, fallos de las copias y de Cloud
  Scheduler, a admin@, un correo por hora como mucho. 404 y 405 se anotan como
  `warning` en las cinco (los robots que buscan `/.env`).»
- *Ayuda*: «se carga en la pestaña *Ayuda* → "Cargar el fichero"».
- *No hacer sin que lo pida el usuario*: comillas del usuario: el resumen
  diario de toda la suite («quiero revisarlo bien»); registro de cambios de
  permisos y repaso anual («no corren prisa»).
- *Ideas sin repo*: «voluntariado para la fiesta de fin de curso ("hay
  tiempo")» y «(justificar antes por qué no basta MiAmpa)» con el resto del
  texto igual.
- *Trampa de Neon*: «`compute_time_seconds` es la CPU usada, no lo que se cobra
  (CU asignada × tiempo encendida)» y «La cifra no queda en el registro: se ve
  en la respuesta de `POST /api/latido`.»
- *Estructura*, el detalle por carpeta:

```
  Application/User/  RegisterUser, UpdateUser, ChangeOwnContact, GrantAccess, RecordVisit
  Application/Calendar/  DefineSchoolYear
  Application/Help/  FaqFile (la forma de faq.json), LoadFaq
  Application/Heartbeat/ RunHeartbeat y sus puertos (Neon, copias, despertar aplicaciones)
  Infrastructure/
    Security/        Google, SessionTokens (HS256), SessionCookie, el autenticador de la
                     cookie, BearerCaller (persona o cuenta de servicio), CrossSiteRequestGuard
    Http/            controladores, presentadores y HelpCors
    Persistence/     Doctrine con mapeo XML y un tipo por value object
    Heartbeat/, Google/  HttpApplicationWaker, CloudRunBackupLauncher, NeonDatabaseUsage, MetadataServer
cliente/src/         PortalAuthenticator, HttpPortal / FakePortal, PortalUser, SuiteRecipients,
                     SuiteMembers, SuiteCalendar, MeController, SignOutController,
                     HeartbeatController, ApplicationRoles y MeExtension
```

---

**Documentos en producción (03/10/2026)**

- Commit `cb2149f` «Documentos en el catálogo; @ampa/ui 0.2.7», push a
  `main`; desplegado por el usuario: revisión **`ampa-portal-00020-5b7`**. Es
  el alta del punto 17 (abajo), que estaba sin commit: `documentos` en el
  catálogo (roles `miembro`, `junta`, `admin`), `URL_DOCUMENTOS`, CORS del
  5179 en local, `BASE_DOCUMENTOS` en el job de copias y `@ampa/ui` **0.2.7**
  (el icono de Documentos, ya publicada).
- El job de copias, vuelto a preparar con `deploy/copias/preparar.sh` una vez
  creado el secreto `documentos-database-url`. Falta lanzarlo una vez a mano
  para ver la copia de `documentos`.
- Tests: 99 unitarios, 79 de integración, 43 del cliente y 34 de Vitest;
  lint y build bien. La primera pasada de integración falló por chocar con
  otros tests en la misma base (`suite_test`); repetida, verde.
- [`ampa-documentos`](../ampa-documentos/CLAUDE.md) quedó desplegada ese día,
  a falta del certificado del dominio.

---


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
  (el de toda la suite), `europe-west1`. Migraciones aplicadas en Neon (al
  desplegar, desde el 28/09/2026; antes al arrancar). Dirección fija, la que usarán los servidores de las aplicaciones
  (`PORTAL_URL`): **`https://ampa-portal-273203000301.europe-west1.run.app`**.
- **`portal.ampasainzvicuna.com`** asociado al servicio (domain mapping). El
  CNAME `portal` → `ghs.googlehosted.com` en CDmon lo pone el usuario; la
  raíz del dominio es la web estática de Firebase y no se toca.
- `gcloud` del portal usa el volumen de sesión de listados
  (`ampa-listados_gcloud_config`, ver `docker-compose.yml`).
- Secret Manager: las claves de firma viejas de fichajes y listados
  (`jwt-private-key`, `jwt-public-key`, `jwt-passphrase`, `listados-jwt-key`)
  **ya no existen**: comprobado el 26/09/2026 con `docker compose run --rm
  gcloud gcloud secrets list`. Quedan 8 secretos con una versión activa cada
  uno (`app-secret`, `database-url`, `facturacion-database-url`,
  `listados-database-url`, `mailer-dsn`, `portal-database-url`,
  `portal-jwt-key`, `tareas-database-url`): 6 gratis por cuenta de
  facturación y ~0,12 $ al mes por los otros dos. Para ver las versiones
  activas de uno: `gcloud secrets versions list NOMBRE --filter=state=ENABLED`.

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
- **Cliente 0.1.2 (26/09/2026, desde la sesión de tareas; publicado y
  desplegado con permiso del usuario)**: `/api/personas` devuelve también
  `notificationEmails` (a dónde van los avisos de cada persona, según su
  ficha) y `Member::getNotificationEmails()` (vacío = el de la cuenta, para
  un portal anterior). Solo añade. 66 tests de PHP y 21 del cliente en
  verde. Commit `8489707`, release `v0.1.2` (sha1
  `c166abdecf2044e0724ff1bd6abfb006ef4cd3e5`), revisión
  `ampa-portal-00006-5h6`.
- **Cliente 0.1.3 (26/09/2026, desde la sesión de tareas; publicado y
  desplegado con permiso del usuario)** (camino (a) del `CLAUDE.md` de
  tareas: el resumen diario de las 5:00 corre sin persona con sesión).
  - Portal: `/api/personas` y `/api/avisos` aceptan, además de la sesión de
    una persona, el **token de identidad de Google de la cuenta de servicio
    de la suite** (`BearerCaller` → `Caller::person()` o
    `Caller::application()`; `GoogleServiceAccountVerifier` comprueba firma,
    audiencia `SUITE_TOKEN_AUDIENCE` = la dirección fija del portal, y
    cuenta en `SUITE_SERVICE_ACCOUNTS` = la de Compute por defecto). Una
    aplicación puede preguntar por cualquier aplicación (todas corren con
    la misma cuenta; el portal no distingue cuál llama). `/api/acceso` no
    lo acepta. Las claves de Google, en `GooglePublicKeys` (compartidas con
    `GoogleIdTokenVerifier`). En desarrollo las dos variables van vacías y
    no se acepta ninguno. `deploy/desplegar.sh` las pone.
  - Cliente: `ApplicationIdentity` (`MetadataServerIdentity`: el servidor
    de metadatos de Cloud Run, audiencia `portal_url`, `format=full`) y
    `CallerToken`: `SuiteMembers` y `SuiteRecipients` usan la cookie de la
    petición y, si no hay, el token del servidor. `FakeApplicationIdentity`
    para los tests (FakePortal lo acepta en `members()`/`recipients()`).
    Solo añade: tercera cifra.
  - 31 unitarios y 43 de integración de PHP, 24 del cliente, en verde.
  - **Publicado y desplegado el 26/09/2026**: commit `9aa0ae0`, release
    `v0.1.3` (sha1 `69a8a60c52408baa80a4750b7331718489114f61`, comprobado
    descargando el zip), revisión `ampa-portal-00007-88t` con las dos
    variables. **Comprobado en producción**: el trabajo de Cloud Scheduler
    de tareas hizo `/api/personas` con el token del servidor y el portal
    contestó 200. Tareas ya está en la 0.1.3.
  - *Saltar entre aplicaciones* (pendiente 5) pasa a ser la **0.1.4**.

**Pendiente, en este orden**

1. ~~Certificado~~ de `portal.ampasainzvicuna.com`: **funciona** desde el
   24/09/2026 (tardó ~50 minutos tras el CNAME), y **se entra con Google**
   por ahí (confirmado por el usuario el 26/09/2026).
2. ~~El primer administrador~~ **Hecho** con `deploy/dar-permisos.sh`:
   **Admin** (`admin@`: portal·admin, fichajes·admin, listados·usuario;
   segundo correo el personal del usuario, avisos a los dos) y **Alberto**
   (`info@`: listados·usuario, fichajes·empleado).
3. ~~Adoptarlo en las tres aplicaciones~~ **Hecho en el código.**
   Facturación fue la primera (25/09/2026) y está desplegada. **Listados y
   fichajes, el 25/09/2026 por la tarde** (Claude, "hazlo tú"), con tests en
   verde y probados contra este portal en local; **desplegados el 25/09/2026**
   (`docs/despliegue.md` de cada una, *Pasar al portal*). El usuario comprobó
   ese día que **la sesión viaja entre aplicaciones**; los secretos viejos
   ya están destruidos (arriba). Fichajes se desplegó con
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
4. ~~**Cuentas que no son del Workspace**~~ **Hecho** (confirmado por el
   usuario el 26/09/2026): Cloud Identity Free montado y funcionando en
   producción, con sus usuarios dados de alta en el portal. Lo que sigue es
   el porqué, por si hay que repetirlo.
   (Pedido por el usuario el 25/09/2026, "importante"): dar entrada a gente sin pagarle una cuenta del Workspace,
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
   tiene *Cloud Identity Free*. (Comprobado por el usuario el 27/09/2026,
   antes de que acabe la prueba del Workspace: todo correcto.)
   **Aceptado por el usuario el 25/09/2026**: los que se den de alta así no
   van a mirar ningún correo del AMPA y no tienen que recibir nada en esa
   dirección. Ojo con tareas: sus avisos (asignaciones, vencimientos,
   resumen diario) sí son para esas personas, así que en su ficha del
   portal va su correo personal como **segundo correo** con los avisos a
   *segundo*. Si no, los avisos rebotan contra una dirección sin buzón.

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
   (0.1.4, porque la 0.1.2 y la 0.1.3 son de tareas: solo añade) y `@ampa/ui` lo pinta en `AppShell`. Después, cada
   aplicación sube de versión y se redespliega.
   **Hecho el 26/09/2026 por Claude** (el usuario fuera, "siempre acabando
   en commit y despliegue"): commit `2651c3e`, release **`v0.1.4`** (sha1
   `5a5d5a2f271fe497fb8402ef4218db9a008a9db1`, comprobado descargando el
   zip). `ReachableApplication`, `PortalAccess::getApplications()` (vacía
   con un portal anterior), `PortalUser::getApplications()`,
   `FakePortal::session(…, $applications)`. El front del portal, en
   `@ampa/ui` 0.2.2, con el selector también en su barra y los iconos de las
   tarjetas de `ApplicationIcon`. 26 tests del cliente, 75 de PHP y 14 del
   front. Las aplicaciones, cada una en su `CLAUDE.md`. **En producción en
   todas y funcionando** (confirmado por el usuario el 26/09/2026).
6. ~~Tareas: su repositorio en GitHub y su despliegue~~ Hecho.
7. ~~**Última entrada de cada persona en *Permisos***~~ **Hecho el
   26/09/2026 por Claude** ("hazlo tú"; **sin commit ni despliegue**).
   Sirve para ver quién se dio de alta con un correo mal escrito (la ficha
   se busca por el correo exacto y esa persona no llega a entrar nunca).
   - `User::recordVisit($now)` guarda `lastSeenAt` **como mucho una vez por
     hora** (las aplicaciones preguntan en cada petición); lo llama
     `RecordVisit` (aplicación) al entrar con Google, en `/api/me` del portal
     y en **`/api/acceso`**, que es donde se ve de verdad: casi nadie pasa
     por el portal, se entra directo en cada aplicación. Si guardar falla, se
     anota en el registro y la petición sigue: nunca impide entrar.
   - Columna `last_seen_at TIMESTAMP(0) WITH TIME ZONE` (migración
     `Version20260926180000`; se aplica sola al arrancar en Cloud Run).
     `/api/admin/users` añade `lastSeenAt` (ISO 8601 o null). **No toca el
     contrato con las aplicaciones** (ni el cliente ni `/api/acceso` cambian
     de forma).
   - En *Permisos*: «correo · Última entrada: hoy / ayer / hace 5 días / el 3
     de junio de 2026» (días de Madrid, `permissions/lastSeen.ts`) y la
     etiqueta **«Nunca ha entrado»** en las fichas activas sin entrada.
   - 32 unitarios y 45 de integración de PHP, 17 del front, lint y build en
     verde; visto en el navegador con la API simulada.
8. **Ancho 900 px** (26/09/2026, pedido por el usuario "para toda la
   suite"): el portal pasa a `md` en todas las secciones (antes `sm`, y `md`
   solo en *Permisos*). La regla, en el `CLAUDE.md` de
   [`ampa-ui`](../ampa-ui/CLAUDE.md), *Reglas del código*.

9. **Mejoras para toda la suite** (26/09/2026, por la tarde; Claude, "hazlo
   tú" con el usuario fuera; **sin commit ni despliegue**). Las propuso Claude
   y el usuario eligió la 1, 2, 3, 5 y 6 de "para toda la suite" (la 4, el
   resumen diario de toda la suite, "quiero revisarlo bien": **no hacer sin
   que lo diga**).
   - **Copias de seguridad** (`deploy/copias/`): job de Cloud Run
     `ampa-copias` (postgres:16-alpine + curl + jq), `pg_dump` de las 5 bases
     con sus secretos de siempre → unidad compartida de Drive, 30 por base, las
     viejas a la papelera. Probado en local: copia, restauración en una base
     nueva y que una base que falla no frena a las demás. **La subida a Drive
     sin probar** (hace falta Google). Montarlo: su README.
   - **Calendario escolar común**: `Domain/Calendar` (`SchoolYear`,
     `CalendarPeriod`, `DayKind` festivo/no lectivo), tabla `school_years`
     (migración `Version20260926200000`), `GET /api/calendario?curso=`
     (sesión o cuenta de servicio, sin rol), `GET|PUT /api/admin/calendario`,
     pestaña *Calendario* en el front (solo admin). Cliente: `SuiteCalendar`,
     `SchoolCalendar`, `Portal::calendar()`, `FakePortal::calendarIs()`.
     Visto en el navegador con una cookie de desarrollo: guardar un curso
     (comprobado en la base) y el viaje real `HttpPortal::calendar()` contra
     el portal levantado. **Sin datos**: el usuario carga el curso 2026/27.
     Facturación (Cutasa) y fichajes (festivos) lo adoptan después, cada una
     en su sesión.
   - **Latido diario** (`POST /api/latido`, `RunHeartbeat`, `app:latido`):
     un solo trabajo de Cloud Scheduler (`suite-latido`, 4:00, 
     `deploy/programar.sh`) → Neon (aviso ≥ 80 % de 100 CU-horas;
     `compute_time_seconds` del proyecto, **contrastar con la consola la
     primera vez**), lanza `ampa-copias` y despierta a las aplicaciones con
     `latido: true` en `suite.yaml` (**ninguna todavía**). Siempre 200; los
     fallos, `[error]` en el registro. `--timeout` del portal de 60 a 300 s.
     Cliente: `POST /api/latido` → evento `HeartbeatReceived`, comprobado con
     `GoogleTokenInfoVerifier` (tokeninfo de Google: sin firebase/php-jwt en
     el cliente), `latido_audiencia`/`latido_cuentas`,
     `FakeHeartbeatVerifier`.
   - **Alertas** (`deploy/alertas.sh CORREO`): una política de Cloud
     Monitoring sobre los registros: `[error]` de cualquier aplicación (todas
     usan el registro mínimo de Symfony, ninguna monolog), 5xx, fallos del
     job de copias y de Cloud Scheduler; un correo por hora como mucho. Gratis
     hasta, como pronto, el 1/9/2027. Con la API REST (sin `gcloud alpha`).
   - **Usuario propio de fichajes** (punto 6; era la única que entraba con el
     dueño de Neon): `ampa-fichajes/deploy/base-propia.sh` +
     `copiar-base.sh` y la guía en su `docs/despliegue.md`, *Base y usuario
     propios*. La copia, probada en local. Sin ejecutar: la base nueva la
     crea el usuario en Neon.
   - **Cliente 0.1.5** (solo añade: tercera cifra; ninguna aplicación tiene
     que cambiar nada para subir). La interfaz `Portal` gana `calendar()`:
     ninguna aplicación la implementa (comprobado con grep).
   - 63 unitarios y 58 de integración de PHP, 43 del cliente, 24 del front,
     lint, tipos y build en verde; shellcheck limpio en los scripts.
   - **Publicado y desplegado el 27/09/2026** (con permiso del usuario):
     commit `a47434a`, release **`v0.1.5`** (sha1
     `20260e590538aa64ccf8c56584df44c165c2d3c8`, comprobado descargando el
     zip), revisión **`ampa-portal-00010-bh9`** (migración del calendario
     aplicada en Neon). Trabajo de Cloud Scheduler **`suite-latido`** creado
     y lanzado a mano: **200** (Neon y copias, "saltado": sin montar).
   - **Montado el 27/09/2026** con los datos del usuario:
     - Copias: unidad compartida `0AOiXVV5m11pCUk9PVA`, job `ampa-copias`
       (Invocador del portal sobre el job; hubo que reintentarlo por
       "concurrent policy changes" y el script ya reintenta solo). Primera
       ejecución a mano y otra lanzada por el latido: **5 `.dump` en Drive**.
     - Neon: secreto `neon-api-key` (lo creó el usuario) y proyecto
       `small-pond-03723796`. Primera lectura (27/09): **1,6 de 100 CU-horas**.
       **No cuadra con la consola**: el 29/09/2026 la consola de Neon decía
       **9,94 CU-horas** «Since Sep 22» (64 MB de almacenamiento). El latido
       se queda corto, así que el aviso del 80 % llegaría tarde o nunca.
       **Arreglado el 29/09/2026** (Claude; commit `7b4746a`, revisión `ampa-portal-00018-7nw`):
       `compute_time_seconds` es la CPU usada de verdad (vale lo mismo que
       `cpu_used_sec`: 5.611 s), y Neon cobra la CU **asignada** × el tiempo
       encendida (`active_time_seconds`: 21.272 s; la base escala de 0,25 a
       2 CU). La cifra exacta solo la da `consumption_history`, que no existe
       en el plan gratuito. Ahora el latido da **el máximo posible**: tiempo
       encendida × `autoscaling_limit_max_cu` más grande (de
       `/projects/{id}/endpoints`): «como mucho 11,8 de 100 CU-horas». El
       aviso sale antes de tiempo, nunca tarde. A ese ritmo (~10 CU-horas por
       semana) el mes gasta ~43. La cifra del latido no queda en el registro
       (en producción solo se escribe de `error` para arriba): se ve en la
       respuesta de `POST /api/latido`.
     - Alerta a `admin@ampasainzvicuna.com`. Saltó una vez con los registros
       de auditoría de ese permiso fallido: ahora se excluyen
       (`NOT logName:"cloudaudit.googleapis.com"`).
     - Revisión **`ampa-portal-00011-rtg`** con `COPIAS_JOB`, `NEON_*`.
   - El curso 2026/27 **ya está cargado** en *Calendario* (el usuario, el
     27/09/2026 por la mañana). La base propia de fichajes queda **pendiente
     por decisión suya** ("no corre prisa", 27/09/2026).
   - **Subir las aplicaciones a la 0.1.5** (27/09/2026 por la tarde, Claude,
     "hazlo tú", y commit y despliegue con permiso del usuario): las cuatro al
     cliente 0.1.5 y desplegadas (fichajes `00017-jvt`, tareas `00012-nr7`,
     listados `00015-zzj`, facturación `00009-j9t`), y fichajes y tareas con
     el latido. **Portal `12b9916`, revisión `ampa-portal-00013-cj9`**:
     `latido: true` para fichajes y tareas (el test `HeartbeatApiTest` espera
     ahora `neon`, `copias`, `fichajes` y `tareas`, porque lee el mismo
     `suite.yaml`). Lanzado a mano el 27/09 a las 20:11: tareas bien; **fichajes
     401** porque su Apache no pasaba `Authorization` (arreglado en fichajes
     `2b21b3a`, revisión `ampa-fichajes-00018-qlz`). **Comprobado el 28/09 a
     las 19:07**: fichajes y tareas contestan 200 al latido. Trabajo
     `tareas-resumen-diario` **borrado**: en Cloud Scheduler solo queda
     `suite-latido` (2 de los 3 gratuitos libres).

10. **Entrar sin ventana emergente y la alerta sin robots** (27/09/2026,
    Claude, "hazlo tú y commitea y despliega").
    - **Por qué**: a alguien con Android, al pulsar el botón de Google se le
      abría una pestaña que se quedaba en `about:blank` y no podía entrar
      (vídeo que le mandaron al usuario). Las cabeceras del portal no tenían
      la culpa (sin COOP ni CSP): era la ventana emergente de GIS en ese
      móvil.
    - **Modo redirección** (`@ampa/ui` **0.2.3**): la página va a Google y
      Google vuelve con un POST de formulario a **`POST
      /api/auth/google/vuelta`** (`AuthController::signInReturn`), que
      comprueba la cookie `g_csrf_token` de Google contra el campo del mismo
      nombre (Google la pone para 5 minutos, `SameSite=None`), el token, y
      lleva con 303 a `/?entrada=ok|sin-acceso|no-valida|caducada` (+
      `&volver=` con el `state` de GIS). `CrossSiteRequestGuard` deja pasar
      solo esa ruta (el POST viene de accounts.google.com). `/api/auth/google`
      (JSON) se queda para páginas abiertas con la versión anterior.
    - **Requisito en Google Cloud**: la ruta en los *URI de redirección
      autorizados* del cliente OAuth (README, *Desplegar* paso 6 y
      *Desarrollo*). Sin eso Google contesta `redirect_uri_mismatch`.
    - **404/405 como `warning`** en `framework.yaml` de las cinco: Symfony
      anota cualquier 4xx como `[error]` y los robots que buscan `/.env`
      disparaban la alerta (llegó un correo a las 17:48). En producción el
      registro de Symfony solo escribe de `error` para arriba, así que ya ni
      aparecen. `/.env` y `/.git/config` devuelven la portada (el
      *fallback* del front), no el fichero: comprobado. Commits y
      despliegues de fichajes, listados, facturación (desde un worktree:
      había otra sesión trabajando) y tareas (ídem; subió también su
      `ee0178e`, que ya estaba desplegado).

11. **Arranque en frío más corto** (28/09/2026, Claude, "hazlo tú", con
    commit y despliegue). El usuario, desde el móvil de alguien de la junta,
    veía primero **la página de 404 de Chrome** y a los pocos segundos el
    botón de Google. En los registros no hay ningún 404 del portal: coincide
    con el **arranque en frío** (la portada tardó 4,4 y 4,7 s las dos veces
    de ese día). La página de error no queda en los registros de Cloud Run
    y no se sabe con certeza quién la pone; se ataca el arranque.
    - `docker/prod/entrypoint.sh` ya **no migra ni hace `chown`**: solo
      Apache. En local, de ~3,7 s a ~1,8 s hasta el primer 200.
    - `deploy/desplegar.sh`: revisión nueva con `--no-traffic` → job
      **`portal-migraciones`** (su imagen, `php bin/console
      doctrine:migrations:migrate`) → `update-traffic --to-latest`. Si la
      migración falla, el tráfico se queda en la anterior. Probado en local
      el comando del job: base vacía, nada pendiente y base que no contesta
      (sale con 7).
    - `GET /api/auth/google/vuelta` → 303 a `/` (antes 405): la visitaban
      robots de Google (Chrome/151, `Google-Read-Aloud`) ~5 s después de
      cada entrada desde Android.
    - `--min-instances=1` quitaría el arranque del todo: **~10 $ al mes**
      (tarifa de inactividad, 0,0000025 $/s por vCPU y por GiB).
      **Descartado por el usuario el 29/09/2026.**

12. **Ayuda de la suite (28/09/2026; terminada el 29/09/2026, abajo).**
    Pedido por el usuario: FAQ con buscador en la barra de todas las
    aplicaciones, gratis y sin IA; si no encuentra, «Preguntar al asistente»
    abre el cuaderno de NotebookLM
    (el enlace, en `../ampa-manuales/CLAUDE.md`: este repositorio es público)
    (comprobado por el usuario) y da el correo de admin@. Tres piezas en
    paralelo: (1) contenido en `ampa-manuales/ayuda/faq.json` (privado, fuera
    de los repos públicos) y su PDF 07; (2) aquí, `GET /api/ayuda` (sesión,
    filtrado por roles, CORS a los orígenes de la suite),
    `PUT|GET /api/admin/ayuda`, `app:ayuda:cargar` y la pestaña *Ayuda*
    (admin); (3) `@ampa/ui` 0.2.5 (era la 0.2.4; ver el punto 14): botón «Ayuda» y panel con buscador en
    `AppShell`. Después, en orden: el usuario revisa las preguntas → publicar
    `@ampa/ui` 0.2.5 → commit y despliegue del portal (migración nueva) →
    cargar faq.json en la pestaña *Ayuda* → subir `@ampa/ui` a 0.2.5 en el
    front del portal y de las cuatro aplicaciones y desplegarlas.
    - **Pieza (2), el portal: HECHA** (Claude, 28/09/2026; commit y
      despliegue el 29/09/2026). El detalle, en el README, *La ayuda de la suite*.
      - Dominio `Domain/Help/`: `Faq` (una sola fila, tabla `faq`, con las
        preguntas en JSONB; `publish`/`replace` sustituyen todo y comprueban
        ids únicos, aplicación y roles contra el catálogo, `notebookUrl`
        https, correo y fecha), `FaqEntry` (`isVisibleWith(Grants)`: las
        `general` todos; las de una aplicación, quien tiene algún rol allí
        y, con `roles`, uno de ellos), `FaqError`, `FaqRepository`.
        `Application/Help/`: `FaqFile` (la forma de faq.json, `version` 1,
        errores con el id o la posición de la pregunta) y `LoadFaq`.
        Migración `Version20260928120000` (**solo añade** la tabla).
      - Rutas: `GET /api/ayuda` (cookie, `HelpController`; contrato con
        `@ampa/ui`: `{notebookUrl, contactEmail, updatedAt, entries: [{id,
        application, question, answer, keywords, manual}]}`, sin `roles`),
        `GET|PUT /api/admin/ayuda` (`AdminHelpController`; el PUT recibe el
        faq.json entero, 400 si no es JSON, 422 con el motivo; los dos
        devuelven `{loadedAt, updatedAt, notebookUrl, contactEmail, total,
        byApplication: [{application, name, entries}]}`). Comando
        `app:ayuda:cargar <fichero>` (`LoadFaqCommand`).
      - **CORS** (`Infrastructure/Http/HelpCors`, un listener, sin bundle):
        solo en `/api/ayuda`; `Access-Control-Allow-Origin: <origin>` +
        `Allow-Credentials` a las `URL_…` del catálogo, `DEFAULT_URI` y
        `HELP_ALLOWED_ORIGINS` (variable nueva: en `api/.env` los Vite
        5173-5177; en `deploy/desplegar.sh`, **vacía**). `Vary: Origin`
        siempre; también en el 401 (si no, el front vería un error de red).
        OPTIONS se contesta con 204 antes del router y del cortafuegos.
        `CrossSiteRequestGuard` no la frena (solo mira POST/PUT/…).
        Comprobado con curl por nginx y por el Vite del portal.
      - Front: pestaña **Ayuda** (`web/src/help/Help.tsx`, solo admin, en su
        propio trozo con `lazy`: con ella dentro, el trozo principal pasaba
        de los 600 kB del aviso de Vite; desde el 29/09/2026 va con *Permisos*
        y *Calendario*, también aparte: el principal, en 375 kB). Visto en el navegador con una
        cookie de desarrollo.
      - Tests: 97 unitarios y 79 de integración de PHP, 43 del cliente, 30
        del front, lint, tipos y build en verde.
      - En la base de desarrollo, desde el 29/09/2026, las 88 preguntas de verdad
        (`app:ayuda:cargar`, para comprobar que el fichero pasa).
      - Comprobado de nuevo el 28/09/2026 (sesión de tareas): 97 unitarios y
        79 de integración en verde, con el rol `junta` de abajo ya en
        `suite.yaml`.
    - **Terminada el 29/09/2026** (Claude, "termina lo de la ayuda"; el
      usuario, preguntado, decidió cargar las preguntas sin revisarlas antes
      y añadir las de tareas privadas y de solo la junta):
      - Juntada con el lavado de cara (punto 14): `@ampa/ui` **0.2.5**
        (`6298ae5`, release `v0.2.5`), portal `ba6f50b`, revisión
        **`ampa-portal-00017-5jj`** (la migración de `faq`, por el job
        `portal-migraciones`). Comprobado: `/api/ayuda` da 401 sin sesión, con
        `Access-Control-Allow-Origin` a tareas y sin él a un origen de fuera.
      - Visto en el navegador con las preguntas de verdad y una API simulada:
        el botón en la barra, buscar, abrir una respuesta, «No está en la
        ayuda» y la pestaña *Ayuda*.
      - Contenido: **90 preguntas** (dos nuevas de tareas; `ampa-manuales`
        `4298c5f`, con el manual 06 y el PDF 07 regenerados). Cargadas en
        producción con un job de un solo uso (`portal-ayuda`, el fichero
        comprimido en una variable, `app:ayuda:cargar`, y el job borrado),
        porque entrar en la pestaña pide la sesión de Google del usuario.
        La próxima vez, lo normal: la pestaña *Ayuda* → «Cargar el fichero».
      - El PDF 06 y el 07, **ya en Drive y en el cuaderno** (el usuario,
        29/09/2026). **Falta que el usuario** lea las preguntas cuando pueda (se corrigen en faq.json y se vuelve
        a cargar). **Sigue pendiente** (repasado con el usuario el
        29/09/2026).

13. **Rol `junta` de tareas** (28/09/2026, Claude, desde la sesión de
    tareas): `junta: 'Junta (ve las tareas de solo la junta)'` en
    `suite.yaml`, además de miembro. Qué hace, en el `CLAUDE.md` de tareas.
    Commit `0446897`, solo ese fichero (lo de la Ayuda sigue sin commit), y
    **desplegado** desde un worktree limpio en `ampa-portal-00015-84b`. En la
    base local, `admin.prueba@example.com` tiene además `tareas:junta` y
    `vocal.prueba@example.com` no. El usuario ya dio `tareas:junta` a la junta
    (no a Alberto; confirmado el 29/09/2026).

14. **Lavado de cara de toda la suite** (28/09/2026, Claude, pedido por el
    usuario: «modernizar un poco la interfaz pero sin que pierda la
    esencia», con commit y despliegue; el 12 y el 13 son de otras sesiones).
    Casi todo está en **`@ampa/ui` 0.2.4** (tema, barra y entrada: su
    `CLAUDE.md`, punto 12), que suben las cinco. Aquí, además:
    - *Aplicaciones*: saludo arriba («LUNES, 28 DE SEPTIEMBRE / Hola,
      Alberto»), y cada tarjeta con el icono en un cuadrado redondeado
      relleno de su color, en vez de la franja de arriba (que cortaba la
      esquina redonda); la flecha se enciende al pasar por encima.
    - *Permisos*: las iniciales de cada persona en un círculo
      (`permissions/initials.ts`), en gris si está desactivada.
    - *Permisos* y *Calendario* se cargan aparte (`lazy`): con el tema nuevo
      el trozo principal pasaba de los 600 kB del aviso; ahora 566 kB.
    - **La Ayuda (punto 12) pasa a ser `@ampa/ui` 0.2.5**, acordado con la
      sesión de tareas. Al juntarla con esto choca en `web/src/home/Home.tsx`
      (las dos cargan secciones con `lazy`: la de la Ayuda puede ir dentro
      del mismo `Suspense`) y en este fichero.
    - Visto en el navegador (escritorio y móvil) con una API simulada; 28
      tests, lint, tipos y build en verde.
    - **Desplegado**: commit `6c4e82e`, revisión **`ampa-portal-00016-99v`**
      (comprobado que `portal.ampasainzvicuna.com` sirve el front nuevo). El
      contenedor de gcloud se cayó a mitad (Docker Desktop) y la revisión
      entró directa con el tráfico, sin pasar por `portal-migraciones`: no
      había ninguna migración pendiente.

15. **Repaso de lo pendiente de toda la suite** (29/09/2026, el usuario punto
    por punto; cada `CLAUDE.md` recoge su parte). Lo que Claude hizo después,
    con commit y despliegue con permiso del usuario:
    - Facturación `5fedc01` → `ampa-facturacion-00013-8zq`: días de clase de
      Cutasa con `SuiteCalendar` (primer uso del calendario del portal),
      recibo devuelto cobrado enlazado con sus dos apuntes y el Cuadrante del
      mes en curso.
    - Listados `8a85ef2` → `ampa-listados-00018-7ks`: **estadísticas**, con
      **`@mui/x-charts`** como librería de gráficos de la suite (pasa a
      `@ampa/ui` cuando otra aplicación la use).
    - Fichajes `dc9d72c` → `ampa-fichajes-00021-8gn` (desplegado con la
      sesión de gcloud de listados: su volumen tiene la sesión caducada) y
      tareas `2f3c589` → `ampa-tareas-00017-544` (fuera la ruta vieja del
      resumen).
    - `ampa-manuales` **ya está en GitHub**, privado
      (`ampa-sainz-vicuna/ampa-manuales`), con el manual 05 (KeePassXC).
    - La sesión de gcloud caducó a mitad; la renovó el usuario con
      `docker compose run --rm gcloud gcloud auth login --no-launch-browser`.
    - El usuario **volvió a cargar `faq.json`** y **subió los PDF** a Drive y
      al cuaderno (29/09/2026). Después, el manual 05 recogió sus decisiones
      y se regeneraron el 01, el 02 y el 05; **también subidos** ese día.
    - **Falta que el usuario** averigüe con Carmen quién da los desayunos en
      2026/27 (cree que ya no es Cutasa) y a cuánto por niño y día, para
      ponerlo en *Alumnos* de facturación; cree «Junta - Contraseñas» y la
      base (manual 05); y diga qué tal las **estadísticas de
      listados** cuando suba el fichero de octubre.
    - **`gh` instalado** (29/09/2026), con la cuenta `ampasainzvicuna` (dueña de
      la organización; permisos `repo`, `read:org`, `workflow`). Está en
      `C:\Program Files\GitHub CLI`; desde Git Bash no se ve hasta reiniciar
      la aplicación: mientras, por PowerShell.
    - El **fallo del latido con Neon** (arriba, punto 9): arreglado ese mismo
      día.

16. **Alta de Proveedores (`crm`)** (29/09/2026, Claude, "hazlo tú. empezamos
    ampa-crm"; commit y despliegue ese día por la tarde, "haz lo que falta":
    commit `68c1c69`, revisión **`ampa-portal-00019-4rp`**, sin migraciones;
    comprobado que lleva `URL_CRM`). La aplicación, hecha en local en
    [`ampa-crm`](../ampa-crm/CLAUDE.md). Aquí:
    - `crm` en `suite.yaml`, con el nombre **«Proveedores»** (el que ve la
      junta) y los roles `miembro` y `admin` (borra proveedores y mantiene las
      categorías). Sin `latido`: no hace nada programado.
    - `URL_CRM` (`http://localhost:5178` en `api/.env`;
      `https://proveedores.ampasainzvicuna.com` en `deploy/desplegar.sh`, la
      dirección prevista), la tarjeta en `web/src/home/Applications.tsx`, el
      5178 en `HELP_ALLOWED_ORIGINS` y `BASE_CRM=crm-database-url` en
      `deploy/copias/preparar.sh` (se salta con un aviso hasta que exista el
      secreto; después, volver a lanzar ese `preparar.sh`).
    - `HelpApiTest` cuenta también `crm` (lee el mismo `suite.yaml`).
    - No toca el cliente ni el contrato con las aplicaciones.
    - En la base de desarrollo, `admin.prueba@example.com` tiene además
      `crm:miembro` y `crm:admin`, y `vocal.prueba@example.com`, `crm:miembro`.
    - 99 unitarios y 79 de integración de PHP y 34 del front en verde.
    - El front, en **`@ampa/ui` 0.2.6** (publicada ese día: el icono de
      Proveedores, `StorefrontRounded`).
    - **Proveedores desplegada el 30/09/2026** (`ampa-crm-00001-5sx`) y
      `ampa-copias` preparado otra vez: lanzado a mano, seis `.dump` en Drive
      con el de `crm`. El portal ya tenía `COPIAS_JOB`: sin redesplegar.

17. **Alta de Documentos (`documentos`)** (03/10/2026, Claude, "hazlo tú";
    **sin commit ni despliegue**). La aplicación, fase 1 hecha en local, en
    [`ampa-documentos`](../ampa-documentos/CLAUDE.md) (lo decidido con el
    usuario, ahí). Aquí, como con Proveedores:
    - `documentos` en `suite.yaml` («Documentos»; roles `miembro` (espacio
      «AMPA»), `junta` (además, «Junta») y `admin` (manda carpetas enteras a
      la papelera)). Sin `latido` (lo necesitará la fase 4, vencimientos).
    - `URL_DOCUMENTOS` (`http://localhost:5179`; en `deploy/desplegar.sh`,
      `https://documentos.ampasainzvicuna.com`, la prevista), la tarjeta, el
      5179 en `HELP_ALLOWED_ORIGINS` y `BASE_DOCUMENTOS=documentos-database-url`
      en `deploy/copias/preparar.sh` (se salta hasta que exista el secreto).
    - `HelpApiTest` cuenta también `documentos`. No toca el cliente ni el
      contrato con las aplicaciones: documentos lee `getApplications()` del
      cliente 0.1.5 para saber a qué unidades de las otras aplicaciones (en
      solo lectura) puede ir cada persona.
    - En la base de desarrollo, `admin.prueba@example.com` tiene
      `documentos:miembro`, `junta` y `admin`; `vocal.prueba@example.com`,
      `documentos:miembro`.
    - 99 unitarios y 79 de integración de PHP, 34 del front y lint en verde.
    - El icono, en `@ampa/ui` **0.2.7, sin publicar** (su `CLAUDE.md`).
    - Ese día el portal local tardaba **15-20 s por petición** (siete Vite
      levantados): más que lo que espera el cliente (15 s). Documentos se vio
      en el navegador con un portal de mentira temporal (su `CLAUDE.md`,
      *Trampas*).

**Ideas del 26/09/2026 que el usuario quiere, repartidas por aplicación**
(apuntadas en el `CLAUDE.md` de cada una; ninguna empezada):
- Facturación: **importar el extracto del banco** ("me encanta, ahorra
  trabajo"), **leer los justificantes** con la API de Claude (**descartado**
  por el usuario el 29/09/2026), y el **informe
  de cuentas del curso para la asamblea**, que es **hacia el 10/10/2026**:
  **hecho y desplegado el 27/09/2026** (su `CLAUDE.md`). La hoja de 2025/26
  **está revisada entera** (662 filas) **e importada en producción** (el
  usuario, 27/09/2026). Falta **cerrar los meses** en *Cuadrante*, de agosto
  de 2025 en adelante (agosto de 2026, después del arqueo con Carmen), y el
  presupuesto de 2026/27: **no tienen presupuesto como tal**; sin él, el
  informe imprime «Sin presupuesto propuesto».
  El aviso de presupuesto le importa menos ("lo calculamos a ojo").
  **Traspaso con Carmen**: guía para Alberto publicada como artefacto el
  27/09/2026 (<https://claude.ai/artifact/RYCc1pbQMVR9p8FF3sFNfi>, privada).
- Tareas: **tareas recurrentes**: **hechas el 27/09/2026 y ya juntadas en
  `main`** (commit `1a643cd`, *Merge remote-tracking branch
  'origin/recurrentes'*).
- Listados: ~~resaltar los cambios de alergias~~ entre un listado y el
  siguiente (**descartado** por el usuario el 29/09/2026). Las
  **estadísticas**: el usuario pide el 29/09/2026 que Claude proponga las
  cifras y las haga.
- Documentos: **protección de menores** (certificados de monitores, LOPIVI,
  seguros, contratos con vencimientos).
- Aplicación nueva: **buzón de las familias**, que además sirva para
  **encuestas y votaciones**; y **voluntariado** para la fiesta de fin de
  curso ("hay tiempo").
- Fichajes: nada ("ok a fichajes como está").
- Portal: registro de cambios de permisos y repaso anual, "me gustan pero no
  corren prisa" (solo él gestiona permisos). Siguen en la lista (repasado el
  29/09/2026).
- Descartado: lotería de Navidad como aplicación (la venderán en MiAmpa como
  producto, con tarjeta); subvenciones, poco prioritario (una al año).

**Ideas del 29/09/2026** (pedidas por el usuario; ninguna empezada):
- Tareas: **subtareas** dentro de cada tarea. **Decidido el 29/09/2026**:
  tareas hijas independientes (su responsable, su fecha, sus avisos) que se
  van marcando como hechas desde la madre. El detalle y lo que queda por
  confirmar, en el [`CLAUDE.md` de tareas](../ampa-tareas/CLAUDE.md), *Estado*.
- **Proveedores y contactos**: una agenda de a quién se llama para cada
  cosa (el hielo de la fiesta, el DJ, el hinchable…), **siempre con
  comentarios** que vayan quedando con los años: «este DJ nos costó X en
  2024 y funcionó bien», «este salió más caro pero la gente flipó». Es la
  memoria de la junta: cuando cambia, no se pierde a quién llamar ni cómo
  fue. MiAmpa no lo hace (es de familias, no de proveedores).
  **Decidido por el usuario el 29/09/2026: aplicación nueva, `ampa-crm`**
  («más un CRM sencillo que otra cosa»), con roles `miembro` y `admin`,
  catálogo de categorías (varias por proveedor), varias personas de contacto,
  comentarios con importe, evento y año aparte y **estrellas de 1 a 5 en cada
  comentario** (el proveedor enseña la media), y adjuntos a Drive. Todo lo
  decidido, lo que queda por confirmar y lo que hay que tocar aquí para darla
  de alta (`suite.yaml`, `URL_CRM`, tarjeta, `HELP_ALLOWED_ORIGINS`, copias),
  en su [`CLAUDE.md`](../ampa-crm/CLAUDE.md).

**Propuestas del 26/09/2026 que el usuario dejó sin prioridad** (no hacerlas
sin que lo pida):
- Avisar en la ficha si una cuenta del dominio (quizá de Cloud Identity, sin
  buzón) deja los avisos en *principal*: "a priori no me preocupa".
- Recordar en la ficha el alta con contrato al marcar *Fichajes: empleado*:
  **descartado**, no debería haber más empleados.
- Que el diálogo de la ficha no se cierre al pulsar fuera perdiendo lo
  escrito, y un buscador en *Permisos*: cero prioridad.



