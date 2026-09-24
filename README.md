# ampa-portal

El **portal del AMPA**, que es a la vez el **back común** de la suite: la
única pieza que habla con Google, la única dueña de **quién entra en qué
aplicación y con qué rol**, y la que da la sesión de toda la suite. Entras una
vez y vale para fichajes, listados, facturación y las que vengan.

Diseño decidido con el usuario el 24/09/2026. La historia, las alternativas y
las consecuencias están en la
[hoja de ruta de la suite, sección 4a](../ampa-fichajes/docs/hoja-de-ruta.md).

Repositorio **público** (`https://github.com/ampa-sainz-vicuna/ampa-portal`),
por lo mismo que `ampa-ui`: las aplicaciones descargan el cliente de la release
sin credenciales. Por eso **no hay ningún correo real en el código ni en las
migraciones**.

---

## Cómo funciona

```
  navegador ──(1) Google──▶ portal.ampasainzvicuna.com
      │                        │ verifica a Google, mira los permisos
      │ ◀──(2) cookie ampa_sesion, Domain=.ampasainzvicuna.com
      │
      │ (3) GET /api/lo-que-sea  (la cookie va sola)
      ▼
  listados.ampasainzvicuna.com ──(4) GET /api/acceso?aplicacion=listados──▶ portal
      (cliente, en su servidor)   Authorization: Bearer <token de la cookie>
                                ◀── { email, name, roles: ["usuario"], notificationEmails }
```

1. En el portal se entra con Google. El portal comprueba que Google lo firmó y
   que esa persona está dada de alta, activa y con algún permiso.
2. Pone una cookie **`HttpOnly`, `Secure`, `SameSite=Lax`** para todo
   `.ampasainzvicuna.com`, con un token HS256 que solo lleva el correo y la
   caducidad (una semana).
3. El navegador la manda sola a la API de cada aplicación (mismo origen, sin
   CORS).
4. El **cliente** que lleva cada aplicación (`cliente/`) coge el token y le
   pregunta al portal, **en cada petición**, qué roles tiene esa persona allí.
   Quitar un permiso o desactivar a alguien surte efecto al momento.

Por qué así, en corto:

- **La clave de firma está solo en el portal.** Las aplicaciones no comprueban
  la firma: preguntan. Así es **un secreto para toda la suite** (el nivel
  gratuito de Secret Manager son seis por cuenta de facturación), y fichajes y
  listados se quitan de encima lexik y sus claves.
- **HS256 y no RS256**: quien firma y quien comprueba es el mismo, el portal.
- **Preguntar en cada petición** cuesta una llamada; a cambio, ninguna
  aplicación depende de las tablas del portal y los permisos son al momento.
  Cuando alguien usa una aplicación, el portal suele estar despierto porque
  acaba de pasar por él.
- **Sin credenciales de servicio** entre aplicaciones y portal: con el token de
  alguien solo se pueden saber SUS roles, que es lo que ya sabe quien tiene su
  token. La única excepción, los avisos, está acotada (ver abajo).

---

## Qué hay

```
api/        el servicio (Symfony 7.4): entrar, sesión, permisos, pantalla de permisos
web/        el front (React + @ampa/ui 0.2): entrar con Google, las tarjetas de las
            aplicaciones, "Mis correos" y la pantalla de permisos
cliente/    el bundle que instala cada aplicación (ampa/portal-cliente); su README
            explica cómo
scripts/    empaquetar el cliente
.github/    la Action que publica una versión del cliente
docker/     el entorno de desarrollo
```

El estado del despliegue, en el [`CLAUDE.md`](CLAUDE.md).

### El front

- **Entrar**: el botón de Google (lo pone `SessionGate` de @ampa/ui, porque el
  `SuiteApp` del portal es el único con `google`).
- **Volver**: una aplicación que recibe a alguien sin sesión lo manda aquí con
  `?volver=<su dirección>`. Tras entrar, el portal vuelve allí solo si es una
  de SUS aplicaciones (si no, cualquiera podría usar el portal para llevar a
  otra web). Y solo vuelve sola si se acaba de entrar: si se llega con la
  sesión ya abierta y un `volver`, algo no cuadra (la aplicación no ve la
  cookie) y volver sola haría dar vueltas a las dos para siempre; entonces lo
  ofrece con un botón.
- **Aplicaciones**: una tarjeta por aplicación con algún rol.
- **Mis correos**: el segundo correo y a dónde van los avisos.
- **Permisos** (solo quien los gestiona): la lista por nombre y la ficha de
  cada persona, con una casilla por rol de cada aplicación. En su propia
  ficha no se puede quitar la gestión ni desactivarse (el servidor también lo
  impide).

### Las personas y sus permisos

Una fila por persona (`users`): correo de la cuenta de Google (su identidad,
no cambia), nombre (lo pone quien da el alta, no Google), activa o no, y sus
permisos en una columna JSON: `{"fichajes": ["admin"], "listados": ["usuario"]}`.
Una columna y no una tabla usuario × aplicación × rol: son decenas de
personas, y así comprobar permisos es leer una fila y cambiarlos es sustituir
un valor (el porqué completo, en `Grants`).

Nadie se borra: se desactiva, y con eso pierde el acceso a todo al momento
aunque su cookie siga vigente.

**Dos correos.** La junta entra con su cuenta de Workspace del AMPA (a veces la
del cargo, que pasa de una persona a otra), pero muchos prefieren leer los
avisos en su correo personal. Cada persona tiene un **segundo correo**
opcional y elige **a dónde le llegan los avisos**: al de la cuenta, al
segundo o a los dos. Lo cambia ella misma desde el portal ("mis correos") o
quien gestiona los permisos. "Los dos" es lo que hacía fichajes con sus
administradores, y es lo que pone el comando si se le da un segundo correo sin
decir más, para que migrarlos no cambie nada.

### Las aplicaciones y sus roles

En [`api/config/packages/suite.yaml`](api/config/packages/suite.yaml), no en la
base de datos: una aplicación o un rol nuevo llegan siempre con código nuevo.
Qué **significa** cada rol lo decide cada aplicación (fichajes: "empleado"
exige además contrato en vigor). El propio portal es una aplicación más, con el
rol `admin`: quien gestiona los permisos de toda la suite.

### La API

| | | |
|---|---|---|
| `POST /api/auth/google` | `{ credential }` | Entra. Pone la cookie y devuelve lo mismo que `/api/me`. 401 si Google no lo confirma, 403 si no tiene acceso a nada. |
| `POST /api/auth/salir` | | Borra la cookie: sale de toda la suite. |
| `GET /api/me` | cookie | `{ name, email, secondaryEmail, notify, isAdmin, applications: [{ code, name, url, roles }] }` |
| `PUT /api/me/contacto` | cookie | `{ secondaryEmail, notify }`: sus correos. Lo único de su ficha que toca cada uno. |
| `GET /api/admin/users` | cookie, admin | Todas las personas, por nombre. |
| `POST /api/admin/users` | cookie, admin | Alta: `{ email, name, grants, secondaryEmail?, notify? }`. |
| `PUT /api/admin/users/{id}` | cookie, admin | Guarda la ficha entera: `{ name, active, grants, secondaryEmail?, notify? }`. Nadie puede quitarse a sí mismo la gestión ni desactivarse (409). |
| `GET /api/admin/applications` | cookie, admin | Las aplicaciones y sus roles, para pintar las casillas. |
| `GET /api/acceso?aplicacion=X` | Bearer | **Contrato con el cliente.** `{ email, name, roles, notificationEmails }`; 401 si la sesión no vale o está desactivada. |
| `GET /api/avisos?aplicacion=X&rol=Y` | Bearer | **Contrato con el cliente.** A quién avisar: `[{ name, emails }]`. Solo si quien pregunta tiene algún rol en X. |

`notify` es `primary` (la cuenta), `secondary` o `both`.

**Los avisos exponen correos personales** a quien entra en esa aplicación: un
empleado de fichajes, con su token, podría pedir directamente al portal los
correos de los administradores de fichajes. Se acepta porque es lo mínimo para
que fichajes avise a sus administradores sin credenciales de servicio, y
porque son personas de la misma aplicación. Si algún día molesta, la salida es
una credencial de servicio para esa ruta.

**Contra CSRF**, dos barreras: `SameSite=Lax` en la cookie y
`CrossSiteRequestGuard`, que rechaza cualquier petición que cambie algo y
llegue con `Sec-Fetch-Site` distinto de `same-origin` (incluido otro
subdominio del AMPA). La misma barrera va en el cliente.

---

## Desarrollo

Todo en Docker, como el resto de la suite (no hay PHP en Windows). Puertos
elegidos para convivir con las demás aplicaciones:

| | fichajes | listados | facturación | **portal** |
|---|---|---|---|---|
| Web (Vite) | 5173 | 5174 | 5175 | **5176** |
| API (nginx) | 8080 | 8081 | 8082 | **8083** |
| PostgreSQL | 5432 | 5433 | 5434 | **5435** |

El origen `http://localhost:5176` tiene que estar en los *orígenes de
JavaScript autorizados* del cliente de OAuth para que el botón de Google
funcione en desarrollo.

**En desarrollo, el portal tiene que estar levantado para probar cualquier
aplicación** que ya use el cliente: es quien da la sesión. La cookie de
`localhost` vale para todos los puertos, así que entrar en el portal sirve
para todas, igual que en producción.

```bash
docker compose up -d
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php vendor/bin/phpunit --testsuite unit
docker compose exec php vendor/bin/phpunit --testsuite integration
```

La primera vez, la base de datos de los tests:

```bash
docker compose exec php bin/console doctrine:database:create --env=test
docker compose exec php bin/console doctrine:migrations:migrate --env=test -n
```

El front:

```bash
docker compose exec node npm test
docker compose exec node npm run lint
docker compose exec node npm run build
```

El cliente, en su carpeta:

```bash
docker compose exec -w /var/www/html/cliente php composer install
docker compose exec -w /var/www/html/cliente php vendor/bin/phpunit
```

(Desde Git Bash, `-w /var/…` se convierte en una ruta de Windows y falla con
*"Cwd must be an absolute path"*: `MSYS_NO_PATHCONV=1` delante. Desde
PowerShell no pasa.)

### Primer arranque: el primer administrador

Sin nadie dado de alta no entra nadie, tampoco en la pantalla de permisos. El
primero (y pasar al portal los accesos que ya había en fichajes y listados)
se da por consola:

```bash
docker compose exec php bin/console app:permisos:dar admin@ampasainzvicuna.com portal:admin listados:usuario --nombre="Nombre Apellido"
docker compose exec php bin/console app:permisos:dar tesoreria@ampasainzvicuna.com fichajes:admin --nombre="Tesorería" --segundo-correo=alguien@gmail.com
```

Solo suma permisos, nunca quita. `--avisos=primary|secondary|both` elige a
dónde van los avisos.

---

## Desplegar

Un contenedor en Cloud Run, como fichajes y listados: Apache sirve `/api`
(Symfony) y el front compilado (`Dockerfile`, `docker/prod/`). Al arrancar
aplica las migraciones. **Sin estrenar**: la imagen se ha construido y probado
en local el 24/09/2026 (migra al arrancar, sirve el front, `/api/acceso` en
~25 ms con el contenedor caliente), pero los scripts de Google Cloud no se han
ejecutado nunca.

El orden, la primera vez:

1. **Neon**: en el proyecto `ampa`, una base `suite` con su propio usuario
   `suite` (como `listados` con el suyo).
2. `docker compose run --rm gcloud bash deploy/preparar.sh`: crea los dos
   secretos, `portal-jwt-key` (la clave de firma de toda la suite) y
   `portal-database-url`.
3. `@ampa/ui` instalada en `web/` desde la URL de su release (publicada la
   0.2.0 el 24/09/2026).
4. `docker compose run --rm gcloud bash deploy/desplegar.sh`.
5. **Dominio**: `portal.ampasainzvicuna.com` asignado al servicio y el CNAME
   en CDmon (el comando, en `desplegar.sh`). **Tiene que ser en el dominio del
   AMPA**: la cookie es de `.ampasainzvicuna.com` y desde `*.run.app` no vale.
6. **OAuth**: añadir `https://portal.ampasainzvicuna.com` a los orígenes
   autorizados del cliente de Google.
7. **El primer administrador** y los accesos que ya había (fichajes y
   listados):

   ```bash
   docker compose run --rm gcloud bash deploy/dar-permisos.sh admin@ampasainzvicuna.com portal:admin listados:usuario --nombre=Admin --segundo-correo=alguien@gmail.com
   ```

   Corre `app:permisos:dar` dentro de Google, como un *job* de Cloud Run con
   la imagen y los secretos del portal, y luego lo borra: la contraseña de la
   base no pasa por tu ordenador.
8. Primera etiqueta del cliente, **v0.1.0** (abajo), y adoptarlo en listados.

Un secreto de firma para toda la suite y otro para la base: dos de los seis
gratuitos, y fichajes y listados liberan los suyos de firma al adoptar el
portal.

---

## Publicar una versión del cliente

Cuando cambia `cliente/` (o el contrato con él):

1. Pasar los tests de los dos (`api/` y `cliente/`).
2. Commit y `git push`.
3. `git tag v0.2.0 && git push origin v0.2.0`.

La Action (`.github/workflows/publicar.yml`) pasa los tests del portal contra
un PostgreSQL y los del cliente, construye `ampa-portal-cliente-X.Y.Z.zip` y
lo cuelga en la release con su **sha1 en las notas**. Cada aplicación lo
copia en su `composer.json` (cliente/README.md, paso 1).

**Versiones.** Como en `@ampa/ui`: si una aplicación tiene que tocar algo para
subir (cambia una ruta del contrato, el nombre de la cookie, una interfaz del
cliente), sube la **segunda cifra** y las notas dicen qué cambiar en cada una.
Los arreglos que no obligan a nada, la tercera.

Para ver qué va en el zip antes de publicar:

```bash
docker compose exec -w /var/www/html php sh scripts/empaquetar-cliente.sh 0.1.0
```

---

## Trampas conocidas

- **Nunca un `file:` en `web/package.json`** para `@ampa/ui` (sirve para
  probar una versión sin publicar): Cloud Build no tiene esa carpeta y el
  despliegue falla. `deploy/desplegar.sh` se niega a seguir si lo ve.

- **Un identificador de Doctrine tiene que poder convertirse en texto**
  (`UserId::__toString()`): Doctrine guarda las entidades cargadas en un mapa
  cuya clave es el identificador. Sin eso, guardar falla con *"could not be
  converted to string"*.
- **En `when@test` de `services.yaml`, redefinir un servicio le quita el
  autowiring** (ese bloque no hereda los `_defaults`). Para que un test pida un
  servicio al contenedor no hace falta hacerlo público: `static::getContainer()`
  ya da los privados que alguien usa.
- **`TestCase::status()` es `final` en PHPUnit 13**: el ayudante de los tests se
  llama `responseStatus()`.
- **HS256 exige una clave de al menos 32 bytes**; firebase/php-jwt 7 lo
  comprueba y falla con *"Provided key is too short"*.
- **En desarrollo, cada petición al portal tarda 1-2 s** (medido el
  24/09/2026), y una aplicación que use el cliente los suma a cada petición
  suya. Es el disco de Windows compartido con Docker: PHP lee `vendor/` a
  través de él. `opcache.revalidate_freq = 2` (docker/php/php.ini) ya lo bajó
  de ~2,5 s. Si molesta, lo siguiente sería llevar `api/vendor` a un volumen
  de Docker como `var/`, a cambio de que el editor de Windows no vea las
  dependencias. En producción no pasa.
- **La primera petición después de tocar la configuración tarda más** (Symfony
  recompila la caché): si pasa de 15 s, el cliente da el portal por caído
  (503). Recargar.
