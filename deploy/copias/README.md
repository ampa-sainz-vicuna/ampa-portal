# Copias de seguridad de la suite

Cada noche, a las 4:00, el latido del portal (`POST /api/latido`) lanza el
job de Cloud Run **`ampa-copias`**. El job hace un `pg_dump` de cada base de
Neon (portal, fichajes, listados, facturación, tareas, proveedores y, cuando
tenga base, documentos) y lo sube a una **unidad compartida de Drive**. Se guardan las
**30 últimas** de cada base; las más viejas van a la papelera de la unidad,
que Drive vacía a los 30 días.

**Por qué:** hasta ahora no había ninguna copia fuera de Neon. El historial de
Neon gratuito es corto, y todo está en un solo proyecto de una sola cuenta: un
borrado, una migración que sale mal o perder la cuenta se llevaría la
contabilidad y el registro de jornada, que por ley hay que conservar. Las
copias quedan en el Workspace del AMPA y no dependen de Neon.

**Quién tiene las contraseñas:** solo el job, con los mismos secretos que ya
usa cada aplicación. El portal solo puede decirle "arranca" (el rol de
Invocador, sobre ese job y nada más).

**Ojo:** las copias llevan datos personales (el registro de jornada de
Alberto, correos de la junta). La unidad compartida debe tener **solo** a
quien la necesite, igual que *Fichajes - Documentos*.

## Montarlo (una vez)

1. **La unidad compartida** (en Drive, con `admin@`):
   - Drive → *Unidades compartidas* → **Nueva** → `Copias de seguridad de la suite`.
   - *Gestionar miembros* → añadir
     `273203000301-compute@developer.gserviceaccount.com` como **Gestor de
     contenido** (sube y manda a la papelera; no gestiona miembros). Quitar la
     casilla de avisar por correo: es una cuenta de servicio.
   - El **ID** es lo que va detrás de `/folders/` en la barra del navegador al
     abrir la unidad (empieza por `0A…`).
2. **El job** (desde `C:\Users\jimix\dev\ampa-portal`):

   ```bash
   docker compose run --rm gcloud bash deploy/copias/preparar.sh ID_DE_LA_UNIDAD
   ```

3. **Probarlo**:

   ```bash
   docker compose run --rm gcloud gcloud run jobs execute ampa-copias --region=europe-west1 --wait
   ```

   En la unidad tiene que aparecer un `.dump` por base
   (`fichajes-2026-09-27-0400.dump`…). Si falla, el motivo está en Cloud
   Run → *Jobs* → `ampa-copias` → *Registros*.
4. **Que el latido lo lance**: volver a desplegar el portal
   (`deploy/desplegar.sh` ve el job y pone `COPIAS_JOB`) y, si no está,
   programar el latido (`deploy/programar.sh`).

## Restaurar una copia

Nunca encima de la base en uso: primero en una base nueva, se comprueba, y
luego se cambia el secreto de la aplicación para que apunte a ella.

1. Descargar el `.dump` de la unidad.
2. En Neon, crear una base nueva y su usuario, como en el paso 1 del
   `docs/despliegue.md` de la aplicación.
3. Restaurar con la misma imagen del job, que trae el `pg_restore` de la
   versión correcta (la cadena, la de la base NUEVA, sin
   `serverVersion`/`charset`):

   ```bash
   docker run --rm -it -v "%CD%:/copias" --entrypoint pg_restore postgres:16-alpine --no-owner --dbname="postgresql://USUARIO:CONTRASEÑA@SERVIDOR/BASE?sslmode=require" /copias/fichajes-2026-09-27-0400.dump
   ```

   Para sacar una sola tabla, `--table=NOMBRE`; para ver qué hay dentro,
   `pg_restore --list fichero.dump`.

## Probarlo en local

Sin `DRIVE_FOLDER_ID`, las copias se dejan en `/copias`. Contra la base de
desarrollo del portal (probado así el 26/09/2026, y también la restauración
en una base nueva):

```bash
docker build -t ampa-copias-prueba deploy/copias
docker run --rm --network ampa-portal_default -e BASE_PORTAL="postgresql://suite:suite@db:5432/suite?serverVersion=16&sslmode=disable" -v "%CD%/copias-prueba:/copias" ampa-copias-prueba
```
