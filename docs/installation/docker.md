# Docker — Fundación Sprint 00

## Requisitos

- Docker Desktop con backend WSL 2.
- Docker Compose compatible con `!reset` (2.24.4+; validado con 5.4).
- Puertos locales configurables disponibles.
- Copia local de `.env.docker.example` como `.env.docker`, con contraseñas exclusivas de desarrollo.

XAMPP, Apache, MySQL y PHP de Windows no son necesarios para trae.pe. Composer, PHP-FPM, PostgreSQL/PostGIS y Redis se ejecutan dentro de contenedores Linux.

## Puertos

| Servicio | Host por defecto | Red Docker |
|---|---|---|
| HTTP | `127.0.0.1:8000` | `nginx:80` |
| PostgreSQL | `127.0.0.1:54320` | `postgres:5432` |
| Redis | `127.0.0.1:63790` | `redis:6379` |
| Mailpit UI | `127.0.0.1:11825` | `mailpit:8025` |
| SMTP local | No publicado | `mailpit:1025` |
| Reverb | `127.0.0.1:18080` | `reverb:8080` |
| MinIO S3 | `127.0.0.1:19000` | `minio:9000` |
| MinIO consola | `127.0.0.1:19001` | `minio:9001` |
| Horizon | Mediante Nginx `/horizon` | Sin publicación adicional |

Los puertos host se cambian con `TRAEPE_HTTP_PORT`, `TRAEPE_POSTGRES_PORT` y `TRAEPE_REDIS_PORT`. PostgreSQL y Redis nunca se publican en todas las interfaces.

Mailpit agrega `TRAEPE_MAILPIT_HTTP_PORT`; conserva mensajes en `traepe_mailpit_data` y comprueba `/readyz`. Laravel envía por SMTP a Mailpit usando direcciones ficticias `.test`. No se configura relay externo. La imagen fijada es `axllent/mailpit:v1.27.4` con digest en Compose. Los demás puertos se configuran con `TRAEPE_REVERB_PORT`, `TRAEPE_MINIO_PORT` y `TRAEPE_MINIO_CONSOLE_PORT`.

Nginx resuelve API mediante el DNS interno Docker con vigencia 5 s, evitando conservar la IP antigua cuando se recrea API. Al actualizar la configuración Nginx existente, reiniciarlo una vez: `docker compose --env-file .env.docker restart nginx`. No elimina volúmenes.

Los servicios se comunican por `traepe_internal`, una red Docker aislada. Nginx, PostgreSQL y Redis se conectan además a `traepe_host` únicamente para habilitar sus publicaciones ligadas a `127.0.0.1`; PHP-FPM no publica puertos al host.

## Inicio

```powershell
./infrastructure/docker/Initialize-LocalEnvironment.ps1
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker build --pull --no-cache api minio
docker compose --env-file .env.docker up -d postgres redis api
docker compose --env-file .env.docker exec api composer install --no-interaction --prefer-dist
docker compose --env-file .env.docker exec api php artisan migrate --force
docker compose --env-file .env.docker up -d nginx
docker compose --env-file .env.docker up -d --wait
```

## Servicios de 00C

Horizon ejecuta `php artisan horizon` en contenedor separado, supervisa colas `default` y `technical`, timeout 30 s inferior a `retry_after` 90 s, hasta 3 intentos y backoff 1/5/10 s. Entrega al menos una vez: los probes repiten el mismo resultado y los jobs futuros deben ser idempotentes. No hay jobs comerciales. Dashboard y API requieren HTTP Basic técnico y se deniegan fuera de local. La credencial por sí sola no autoriza canales de otro alcance.

Reverb escucha dentro del contenedor y publica solo en loopback. Su healthcheck realiza un upgrade WebSocket real. Canal exclusivo de prueba: `private-technical.v1`; autorización POST `/api/v1/technical/broadcasting/auth`, con credencial técnica y socket válido. Evento `technical.probe.v1` con versión e ID ficticio, sin PII. Los eventos cliente están deshabilitados; no hay garantía de recuperación de eventos perdidos.

MinIO se construye desde commits oficiales fijados según ADR-003. El upstream está archivado y no se elige proveedor productivo. El binario compilado mediante `go install` reporta `DEVELOPMENT.GOGET`; la identidad reproducible es el commit y el digest de imagen, no ese texto de versión. No utilizar la imagen en producción. El build requiere Internet y puede tardar varios minutos en Docker Desktop; Go compila con paralelismo 2 para limitar memoria.

`minio-init` es un inicializador de una sola ejecución: termina con código 0, no permanece healthy. Crea bucket privado y usuario con política limitada; no publica puertos. Solo servidor/inicializador reciben credencial root. Datos en `traepe_minio_data`; API espera inicialización exitosa.

`/up` es liveness independiente. GET `/api/v1/health/ready` es readiness sin secretos ni hosts: 200 `ready`; 503 `unavailable` si falla PostgreSQL/Redis; 503 `degraded` si falla storage, Horizon, Reverb o Mailpit. No devuelve excepciones. Health de PHP-FPM sigue siendo independiente de terceros.

La detección de Horizon utiliza su heartbeat Redis con ventana de 14 s; no es instantánea tras una terminación abrupta. PHP limita DNS a un intento de 1 s, conexión libpq a 2 s mediante `PGCONNECT_TIMEOUT`, Redis a 1/2 s y storage/HTTP a 1/2 s. Estas comprobaciones no prueban entrega duradera de eventos ni sustituyen monitoreo productivo.

```powershell
docker compose --env-file .env.docker exec api php tests/Support/verify-infrastructure.php smoke
docker compose --env-file .env.docker exec api php tests/Support/verify-infrastructure.php queue
docker compose --env-file .env.docker exec api php tests/Support/verify-infrastructure.php websocket
docker compose --env-file .env.docker exec api php tests/Support/verify-infrastructure.php migrations
docker compose --env-file .env.docker exec api php tests/Support/verify-infrastructure.php failed-job
./infrastructure/docker/Verify-HealthFailures.ps1
```

El probe de migraciones crea una base temporal con nombre generado `traepe_00c_probe_*`, verifica dos ejecuciones y elimina únicamente esa base. Nunca reinicia ni limpia la base existente. Los probes queue/storage usan identificadores únicos; correo solo usa destinatarios `.test` capturados localmente.

Para actualizar dependencias en un volumen vendor existente, Composer puede necesitar Internet. La red interna lo impide por diseño. Conectar temporalmente API a `traepe_host`, ejecutar Composer y desconectar siempre en `finally`:

```powershell
$apiContainerId = docker compose --env-file .env.docker ps -q api
docker network connect traepe_host $apiContainerId
try {
    docker compose --env-file .env.docker exec api composer install --no-interaction
} finally {
    docker network disconnect traepe_host $apiContainerId
}
```

## Contratos y módulos de 00D

API, Horizon y Reverb utilizan `traepe/php-fpm:8.4.24-00f`. Recompilar con `docker compose --env-file .env.docker build api` y recrear con `up -d --wait` al cambiar el lock; el volumen vendor existente también requiere `composer install` conforme al procedimiento anterior.

Readiness conserva los códigos HTTP y coloca su estado en `data.attributes.status` y sus dependencias en `data.attributes.checks`, con `meta.correlation_id` y header `X-Correlation-ID`. El script de fallas controladas ya consume esta forma. [OpenAPI](../api/openapi.yaml) y [contratos](../api/README.md) describen la superficie técnica.

`TRAEPE_SANCTUM_STATEFUL_DOMAINS` configura hosts/puertos locales para cookies; si cambia el puerto HTTP, actualizar la lista. Sanctum inicializa CSRF sin login ni tokens. El acceso técnico a Horizon/Reverb conserva la credencial local de 00C.

```powershell
docker compose --env-file .env.docker exec api php tests/Support/lint-openapi.php
docker compose --env-file .env.docker exec api php artisan route:list --except-vendor
docker compose --env-file .env.docker exec api php artisan test
```

El montaje de `docs/api` es de solo lectura y el Dockerfile también lo incluye para validar el contrato sin checkout montado. `make:module` se ejecuta solo al iniciar un módulo autorizado; las pruebas del comando usan un fixture temporal y no crean dominios futuros en el repositorio.

## Operación segura de los datos

- No versionar `.env.docker` ni `apps/api/.env`.
- La clave real de Laravel se inyecta mediante `TRAEPE_APP_KEY` desde `.env.docker`; ambos archivos que la contienen permanecen ignorados.
- No reutilizar credenciales productivas.
- No ejecutar `docker compose down --volumes` como parte de scripts normales.
- Los datos persisten en volúmenes con prefijo `traepe_`.
- Redis es caché/cola reconstruible; PostgreSQL es la fuente de verdad.
- Producción no debe heredar puertos, contraseñas o defaults locales.

## Comprobación

```powershell
docker compose --env-file .env.docker ps
curl.exe http://localhost:8000/up
docker compose --env-file .env.docker exec api php artisan test
```

## Checkpoint 00E: correlación y entrega técnica

El canal `safe` escribe JSON en `apps/api/storage/logs/technical.jsonl`. Solo conserva mensajes técnicos permitidos, ULID de correlación/evento/operación, contadores y tipo de excepción. Elimina mensajes libres, extra, headers, cookies, URL, payload, PII y detalles de excepción. El contexto de Redis transporta únicamente la correlación validada y se limpia entre jobs. Nginx registra JSON con estado, duración y correlación; su error log usa `emerg` para evitar que diagnósticos de requests incluyan URI o datos del cliente. Esto reduce detalle diagnóstico y se compensa con estados y errores sanitizados de aplicación.

El entrypoint aplica grupo `www-data`, directorio de logs `2770` y archivo técnico `0660`, permitiendo escritura compartida de CLI/Horizon y PHP-FPM sin permisos universales.

```powershell
docker compose --env-file .env.docker exec -T api php artisan migrate
docker compose --env-file .env.docker exec -T -e TRAEPE_INTEGRATION_TESTS=1 api php artisan test --no-ansi
docker compose --env-file .env.docker exec -T api vendor/bin/pint --test
docker compose --env-file .env.docker exec -T api php tests/Support/lint-openapi.php
docker compose --env-file .env.docker exec -T api php tests/Support/lint-technical-event.php
docker compose --env-file .env.docker exec -T api php tests/Support/verify-delivery.php
./infrastructure/docker/Verify-CorrelationConcurrency.ps1
```

Las pruebas de integración crean bases `traepe_00e_test_*` aisladas y eliminan únicamente su propia base. Necesitan permiso local de creación de bases; no ejecutar con credenciales productivas. La prueba real conserva filas técnicas ficticias para revisión. El linter de evento necesita al menos uno de esos eventos.

`php artisan platform:dispatch-outbox --limit=100` encola un lote para Horizon en la cola `technical`; no publica desde el proceso HTTP. La entrega es al menos una vez, con inbox transaccional que evita repetir el efecto. Una publicación fallida conserva el evento pendiente y calcula una espera de 1–60 segundos; ejecutar otro lote después de ese plazo. No se incorpora daemon ni calendario productivo. La retención, reutilización de claves vencidas y cadencia productiva requieren especificación posterior; las claves vencidas se rechazan y los eventos permanecen inmutables. Las expiraciones se indican explícitamente por el llamador. El callback de idempotencia participa en la transacción PostgreSQL y no debe realizar efectos externos.