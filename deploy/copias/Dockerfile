# El job de Cloud Run de las copias de seguridad de la suite (ampa-copias):
# pg_dump de cada base de Neon y a una unidad compartida de Drive. Ver
# deploy/copias/README.md.
#
# La MISMA versión mayor de PostgreSQL que Neon (16): un pg_dump más nuevo
# escribe cosas que un PostgreSQL 16 no entiende al restaurar (el 17 añade
# "SET transaction_timeout"), y uno más viejo se niega a copiar. Si Neon pasa
# a la 17, cambiar aquí y volver a ejecutar deploy/copias/preparar.sh.
FROM postgres:16-alpine

RUN apk add --no-cache curl jq

COPY copiar.sh /usr/local/bin/copiar.sh
RUN chmod +x /usr/local/bin/copiar.sh

# No arranca un PostgreSQL: solo usa sus herramientas.
ENTRYPOINT ["/usr/local/bin/copiar.sh"]
