# Instalación y validación de la fundación

Docker es el único runtime oficial. Se requiere Git, Docker Desktop con WSL 2 y contenedores Linux, Compose compatible con `!reset` (2.24.4 o posterior; validado localmente con 5.4) y PowerShell 7. No se requiere PHP, Composer, Node ni XAMPP en Windows. Reserve memoria para compilar MinIO y espacio para las imágenes/volúmenes.

## Primera instalación desde una copia nueva

Desde la raíz del repositorio:

```powershell
./infrastructure/docker/Initialize-LocalEnvironment.ps1
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker build api minio
docker compose --env-file .env.docker up -d --wait postgres redis api
docker compose --env-file .env.docker exec -T api composer install --no-interaction --prefer-dist
docker compose --env-file .env.docker exec -T api php artisan migrate --force --no-interaction
docker compose --env-file .env.docker up -d --wait
docker compose --env-file .env.docker exec -T api composer quality
docker compose --env-file .env.docker exec -T api composer test:integration
docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php smoke
docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php queue
docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php websocket
docker compose --env-file .env.docker exec -T api php tests/Support/verify-delivery.php
docker compose --env-file .env.docker exec -T api php tests/Support/verify-foundation.php
```

El inicializador se niega a sobrescribir archivos existentes. Genera APP_KEY compartida entre ambos archivos y credenciales aleatorias independientes sin mostrarlas. Las plantillas permanecen vacías y los archivos generados están ignorados. Si los puertos están ocupados, edite `.env.docker` y ajuste `TRAEPE_SANCTUM_STATEFUL_DOMAINS` al cambiar HTTP. Consulte [puertos, redes y servicios](docker.md).

El build necesita Internet y puede tardar varios minutos. El volumen vendor nuevo recibe dependencias de la imagen; `composer install` verifica el lock. Si un volumen existente requiere descargas, use el procedimiento de conexión temporal de Composer en [Docker](docker.md), con desconexión en `finally`. No actualice dependencias sin autorización.

HTTP: `http://127.0.0.1:8000/up`; readiness: `/api/v1/health/ready`; Horizon: `/horizon`, usuario `technical` y contraseña del archivo local. Mailpit: `http://127.0.0.1:11825`. Todos los puertos publicados están restringidos a loopback. MinIO es exclusivamente local, con distribución aprobada en DP-024.

## Calidad

| Comando dentro de API | Propósito |
|---|---|
| `composer test` | Pest, Unit/Feature; conserva clases PHPUnit existentes |
| `composer test:integration` | PostgreSQL real con bases temporales aisladas |
| `composer format:check` | Pint sin modificar archivos |
| `composer format` | Aplicar formato Laravel |
| `composer analyse` | Larastan/PHPStan nivel 8, aplicación completa sin baseline |
| `composer contract:check` | OpenAPI y rechazo de contrato inválido |
| `composer quality` | Formato, análisis, contrato y Unit/Feature; falla al primer control fallido |
| `php tests/Support/verify-quality-gates.php` | Confirma exit 1 para errores controlados de Pest/Pint/PHPStan |

`test:integration` necesita privilegio de creación de bases exclusivamente local/CI; las pruebas no reinician la base del desarrollo. Los probes reales conservan filas ficticias de entrega para revisión. No los ejecute en producción.

## Reinicio, diagnóstico y recuperación segura

`docker compose --env-file .env.docker restart` reinicia conservando datos. Después ejecute `up -d --wait` y readiness. Para comprobar persistencia antes y después:

```powershell
docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php persist-write
docker compose --env-file .env.docker restart
docker compose --env-file .env.docker up -d --wait
docker compose --env-file .env.docker exec -T api php tests/Support/verify-infrastructure.php persist-read
```

Si readiness falla, consulte `ps -a`, liveness y `storage/logs/technical.jsonl`. Logs técnicos no incluyen datos del request ni trazas crudas; Nginx conserva estados/correlación. `minio-init` debe terminar con exit 0; no se espera que permanezca healthy. Horizon necesita unos segundos para actualizar heartbeat. Recompile API y reinstale desde lock al cambiar dependencias. Si hay caché de configuración obsoleta, ejecute `php artisan config:clear` dentro de API.

La recuperación normal permite `stop`, `start`, `restart` o recrear contenedores mediante `up`; no borra volúmenes. No ejecutar `down -v`, `migrate:fresh`, `db:wipe`, `queue:flush` ni purgas globales. No se define un reset destructivo del desarrollo: requiere autorización separada y respaldo. Se pueden repetir las migraciones aditivas.

## CI y reproducción aislada

[GitHub Actions](../../.github/workflows/ci.yml) ejecuta en PR, push a main y dispatch manual, con permiso `contents: read`, acciones fijadas por SHA y credenciales efímeras generadas localmente. No utiliza secretos de GitHub ni publica artefactos con logs/entornos. Solo almacena descargas Composer en caché; vendor se instala desde lock.

El workflow invoca [el mismo runner PowerShell](../../infrastructure/ci/Invoke-CI.ps1) con `compose.ci.yaml`: redes/volúmenes llevan prefijo `traepe-ci-*` y no publica puertos. Incluye construcción, Composer validate/audit, migraciones, calidad, integración real, fallas controladas, SMTP/S3/colas/WebSockets, entrega, alcance negativo y persistencia tras reinicio.

En una copia nueva y descartable, sin `.ci-cache/runtime.env` previo:

```powershell
./infrastructure/ci/Invoke-CI.ps1 -Stage All -Project traepe-ci-local
```

La limpieza elimina únicamente recursos temporales del proyecto CI: comprueba identidad del entorno, etiquetas y prefijo de volúmenes antes de removerlos individualmente. Nunca usa `down -v` ni toca `traepe_*` del desarrollo. La caché de descargas permanece en `.ci-cache/composer`; el archivo con credenciales efímeras se elimina. Una ejecución interrumpida se limpia con `-Stage Cleanup -Project` y el mismo nombre registrado; no editarlo para apuntar a otro stack.

Preparar y validar el workflow localmente no demuestra una ejecución verde del servicio GitHub Actions. La publicación del código y su ejecución remota requieren autorización de commit/push; el cierre final también necesita revisión independiente y aprobación explícita del checkpoint.
