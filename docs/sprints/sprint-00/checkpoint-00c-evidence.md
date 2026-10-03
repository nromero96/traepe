# Checkpoint 00C — implementación y evidencia

Fecha: 3 de octubre de 2026. Implementación verificada y aprobada explícitamente por el usuario, quien autorizó comenzar 00D. No se creó commit ni se publicó 00C. Esta evidencia conserva las versiones y resultados observados al terminar 00C; los cambios posteriores se registran en [00D](checkpoint-00d-evidence.md).

## 1. Estado previo y cierre de 00B

`main` tenía árbol limpio y exactamente un commit local de 00B. Los cuatro servicios originales estaban healthy. `git ls-remote` confirmó el padre remoto `24cdb790f79d5fd2a016fa97c7ac5566967db8ee`. El push expresamente autorizado terminó correctamente. Antes de modificar archivos, HEAD y origin/main coincidían en `a96ac04400bfe9d8d24ff142e537059dca813c48`; siguen coincidiendo al finalizar.

Docker Desktop 4.87.0, Engine 29.7.2 y Compose 5.4.0, contexto desktop-linux. Compose sin `--env-file .env.docker` fallaba por variables obligatorias ausentes; el comando documentado funciona. Se utiliza `config --quiet` para evitar imprimir secretos interpolados.

## 2. Decisiones aprobadas

DP-023: el usuario aprobó una credencial técnica local ignorada, sin usuarios ni adelantar Sanctum. HTTP Basic protege Horizon y el endpoint que autoriza exclusivamente `private-technical.v1`. Entorno local por sí solo nunca autoriza. Fuera de local se deniega incluso con credenciales válidas.

DP-024: el usuario aprobó construir MinIO desde código oficial fijado a commits, exclusivamente para desarrollo. El [upstream oficial](https://github.com/minio/minio) está archivado y su distribución es desde código fuente. Se complementaron ADR-003/ADR-005. No se seleccionó proveedor productivo ni un producto sustituto.

## 3. Dependencias y configuración

Horizon 5.50.0, Reverb 1.12.0 y Flysystem S3 3.35.3. Laravel se conserva en 13.26.1, PHP en 8.4.24 y Composer en 2.8.12. La resolución final utilizó `--with-all-dependencies --minimal-changes`: 22 paquetes añadidos, tres cambios de compatibilidad Guzzle 8.0.2 → 7.15.5, promises 3.0.1 → 2.5.3 y psr7 3.0.0 → 2.13.1; elimina polyfill-php82. Las restantes dependencias originales se conservan. No se incorporó tooling de 00F.

Horizon supervisa `default` y `technical`, máximo tres procesos locales, timeout 30 s inferior a `retry_after` 90 s, tres intentos y backoff 1/5/10 s. Probes técnicos idempotentes: repetir entrega conserva el mismo resultado. Fallo terminal registrado y visible en Horizon, seguido de retry explícito exitoso.

Reverb usa orígenes localhost/127.0.0.1 y deshabilita eventos cliente. Canal privado exclusivo de prueba; evento inmutable `technical.probe.v1`, versión 1 e ID ficticio. Firma ligada a socket y canal; canal ajeno, socket inválido y firma incorrecta rechazados.

MinIO: bucket privado `traepe-local`, usuario Laravel `traepe-local-app`, política limitada al bucket. Root solo en servidor/inicializador; no en API. La aplicación no puede crear otros buckets. Correo SMTP interno a Mailpit, sin relay externo ni datos personales.

No hay migraciones nuevas ni reglas comerciales. Los adaptadores técnicos se mantienen fuera de Domain; Platform/Shared no se adelantan a 00D.

## 4. Imágenes

| Imagen | Digest/identidad fijada |
|---|---|
| PHP local `traepe/php-fpm:8.4.24-00c` | índice final `sha256:fde90a9660a1e6738818c5b86ee2dbcc227141121f03f613172d2b02c1fc36be` |
| Base PHP `php:8.4.24-fpm-alpine3.23` | `sha256:71f8fc3f5890b93aefb48602028f1fd93ab61adfe11979953a4cc03bc6e15643` |
| Composer `2.8.12` | `sha256:0d264a0f1e5be23ba363447768df7b30c33d542711ea12e37770ed7b13bf4eaa` |
| Nginx `1.28.0-alpine3.21` | `sha256:09ab424a8c788f8d0fe3a64429f6d19dfa526885c8609b748d0943a75dcb9f8c` |
| PostGIS `17-3.5-alpine` | `sha256:966243672c7d98cb996f26854a790b3b76e3cb77455d6eeb19d72ff82d20e7af` |
| Redis `8.2.1-alpine3.22` | `sha256:f887e6dacdcfa8e14af2f625fdf4474ff8c37dc36ce13b9e89e6e9a901f155ad` |
| Mailpit `v1.27.4` | `sha256:df6c2541907e1be6fac21f509927cf6ed771617a1f4b361ef66d97bd05593d2d` |
| MinIO local `traepe/minio:2025.10.15-00c` | índice `sha256:c1f05415ac6fb6cfdebbca55d002133026c4c4ab0c9ce9d8a8dc07e9202feebf` |
| Builder Go `1.24.8-alpine3.22` | `sha256:3d78beb141d98f42337f1252ecf2a5f20374109929a4c3f6817f9e4179cc0ae5` |
| Runtime Alpine `3.22.2` | `sha256:4b7ce07002c69e8f3d704a9c5d6fd3053be500b7f1c69fc0d80990c2ad8dd412` |

MinIO commit `9e49d5e7a648f00e26f2246f4dc28e6b07f8c84a` (RELEASE.2025-10-15T17-29-55Z); mc commit `7394ce0dd2a80935aded936b09fa12cbb3cb8096` (RELEASE.2025-08-13T08-35-41Z). `go install` verifica módulos y compila con Go 1.24.8. El binario reporta DEVELOPMENT.GOGET; su versión se identifica por commit/digest, no por ese texto. Estos hashes registran este build; no garantizan reproducción bit a bit de timestamps/provenance o paquetes Alpine cambiantes.

## 5. Servicios, puertos y persistencia

| Servicio | Publicación host | Estado final |
|---|---|---|
| api | ninguna | healthy |
| nginx | 127.0.0.1:8000 | healthy |
| postgres | 127.0.0.1:54320 | healthy |
| redis | 127.0.0.1:63790 | healthy |
| horizon | vía Nginx /horizon | healthy |
| reverb | 127.0.0.1:18080 | healthy |
| minio | 127.0.0.1:19000 y :19001 | healthy |
| mailpit | 127.0.0.1:11825; SMTP 1025 interno | healthy |
| minio-init | ninguna | Exited (0), inicializador de una ejecución |

Redes conservadas: `traepe_internal` aislada y `traepe_host` para publicaciones loopback. API/Horizon/inicializador solo en la interna. Reverb/MinIO/Mailpit utilizan ambas. API fue conectada temporalmente a host únicamente para Composer y desconectada después.

Volúmenes conservados: `traepe_postgres_data`, `traepe_redis_data`, `traepe_api_vendor`, `traepe_composer_cache`. Añadidos: `traepe_minio_data`, `traepe_mailpit_data`. Ninguno eliminado. No se ejecutó down -v ni reset de la base existente.

## 6. Variables

Nuevas en plantilla Docker: `TRAEPE_MAILPIT_HTTP_PORT`, `TRAEPE_REVERB_PORT`, `TRAEPE_MINIO_PORT`, `TRAEPE_MINIO_CONSOLE_PORT`; credenciales vacías `TRAEPE_TECHNICAL_PASSWORD`, `TRAEPE_REVERB_APP_KEY`, `TRAEPE_REVERB_APP_SECRET`, `TRAEPE_MINIO_ROOT_PASSWORD`, `TRAEPE_MINIO_APP_PASSWORD`. Valores locales aleatorios e independientes generados solo en `.env.docker` ignorado.

API: `BROADCAST_CONNECTION=reverb`, variables `REVERB_*`, `FILESYSTEM_DISK=s3`, `AWS_ENDPOINT`, bucket/usuario/región/path-style locales, `MAIL_MAILER=smtp`, SMTP mailpit:1025 y remitente `.test`. `APP_DEBUG=false` conservado. `PGCONNECT_TIMEOUT=2` y DNS timeout 1 s/un intento; Redis timeout 1/2 s, SDK/HTTP 1/2 s. Ningún valor local se usa como configuración productiva.

## 7. Comandos y pruebas individuales

Todos los comandos Docker se ejecutaron desde la raíz con `--env-file .env.docker`. Composer y Artisan exclusivamente dentro del contenedor. Builds limpios de API y MinIO con `--pull --no-cache`; build incremental final incorpora ajustes de formato/probes. `up -d --wait` comprobó dependencias saludables e inicializador exitoso.

| # | Prueba requerida | Resultado |
|---|---|---|
| 1 | Compose config | PASS: config --quiet |
| 2 | Build limpio | PASS: API y MinIO; commits/bases fijos |
| 3 | Arranque completo | PASS: up -d --wait |
| 4 | Servicios healthy | PASS: ocho persistentes; init Exited (0) |
| 5 | Laravel/Nginx | PASS: liveness 200, readiness 200 |
| 6 | PostgreSQL/PostGIS | PASS: consulta PostGIS 3.5 |
| 7 | Redis cache/cola | PASS: write/read/delete y jobs reales; productor detecta caída Redis |
| 8 | Horizon supervisión | PASS: status, supervisor y dashboard positivo/negativo |
| 9 | Job real/fallo/retry | PASS: consumo, fallo transitorio, redelivery, fallo terminal visible y retry explícito |
| 10 | Reverb operativo | PASS: healthcheck upgrade 101 |
| 11 | WebSocket | PASS: firma inválida rechazada, autorización privada real, evento recibido, desconexión/reconexión |
| 12 | Persistencia MinIO | PASS: objeto conservado tras reinicio, luego eliminado |
| 13 | Laravel S3 | PASS: escribir/leer/borrar; anónimo 403 y creación de bucket ajeno 403 |
| 14 | Laravel SMTP | PASS: mensaje recibido en Mailpit y conservado tras reinicio |
| 15 | Migraciones limpias | PASS: base temporal propia traepe_00c_probe_*; eliminada solo esa base |
| 16 | Migraciones idempotentes | PASS: segunda ejecución Nothing to migrate |
| 17 | Artisan test | PASS: 7 pruebas, 47 aserciones |
| 18 | Reinicio no destructivo | PASS: PostgreSQL migration state, marcador Redis, objeto MinIO y correo Mailpit preservados |
| 19 | Diff check | PASS |
| 20 | Secretos | Escaneo final por patrones y comparación con valores locales; véase sección final |
| 21 | Revisión diff | Revisados código, configuración, documentación, archivos nuevos y comparación estructural del lock |

Adicionales: Composer validate --strict PASS, Pint --test PASS (46 archivos), nginx -t PASS. Larastan/PHPStan todavía no está instalado: pertenece a 00F; no se declara un resultado de análisis estático inexistente.

Comandos de integración reproducibles: `php tests/Support/verify-infrastructure.php smoke|queue|failed-job|websocket|migrations|persist-write|persist-read` dentro de API. Pruebas de fallos: `./infrastructure/docker/Verify-HealthFailures.ps1`, con `finally` para recuperación.

Se probaron caídas reales de PostgreSQL, Redis, MinIO, Horizon, Reverb y Mailpit. Cada caída devolvió 503 con dependencia down y mantuvo liveness 200. PostgreSQL/Redis producen unavailable; restantes degraded. Recuperación total devuelve ready y seis dependencias up. Payload sin hosts, contraseñas ni trazas.

## 8. Correcciones y límites residuales

La primera prueba de PostgreSQL agotó 20 s; limitar conexión y DNS solucionó la detección. La prueba inmediata de Horizon se ajustó a su ventana upstream de heartbeat de 14 s; no se oculta esa demora. El script de integración corrigió el orden de imports después de Pint; su ejecución final pasó. MinIO puede devolver una lista filtrada de buckets aun sin permisos globales: el rechazo relevante se verifica intentando crear un bucket ajeno, no suponiendo semántica de ListBuckets.

Nginx ahora usa DNS Docker dinámico con vigencia 5 s; la recreación final de API mantuvo HTTP correcto sin reinicio adicional Nginx. Builds desde fuente requieren Internet y varios minutos/memoria en Docker Desktop/WSL2. La parada de Horizon/Reverb puede consumir hasta los 45 s de gracia configurados. Las credenciales HTTP Basic y HTTP/WebSocket sin TLS son exclusivamente locales en loopback. El upstream MinIO archivado y esta imagen no se autorizan para producción; no existe promesa de mantenimiento productivo. WebSocket no garantiza entrega ni replay.

Los probes usan datos ficticios y namespaces técnicos; quedan mensajes de prueba en Mailpit y metadata de jobs para inspección. El probe de persistencia elimina su marcador y objeto al terminar. No hubo reglas, tablas comerciales ni funcionalidades de otro checkpoint.

## 9. Estado de tareas y Git

S00-006, S00-007, S00-008 y S00-009: DONE en implementación y pruebas; revisión del checkpoint pendiente antes de 00D. DP-023/DP-024 resueltas mediante las aprobaciones explícitas. No se crearon commit ni push de 00C. HEAD/origin/main permanecen sincronizados en el hash de 00B; el árbol contiene los cambios locales de este checkpoint.

## 10. Archivos y dependencias exactas

Diff final: 20 archivos existentes modificados y 24 nuevos (44 en total), además de `.env.docker` local ignorado. `git diff --stat` para los existentes: 1967 inserciones y 266 eliminaciones; los archivos nuevos se enumeran abajo y no aparecen en ese stat hasta agregarlos a Git. No se agregó nada al índice.

Escaneo final: 44 archivos modificados/nuevos, cero coincidencias por patrones de claves privadas/tokens/APP_KEY real y cero coincidencias con los valores secretos locales leídos de `.env.docker` sin imprimirlos. `.env.docker` y `apps/api/.env` siguen ignorados. La comprobación por patrones/valores conocidos no equivale a un análisis exhaustivo de seguridad. Composer no reportó avisos de vulnerabilidades durante la instalación.

También se modificó `docs/README.md` para enlazar esta evidencia. Se limpiaron los objetos técnicos residuales de la prueba fallida en el volumen MinIO creado por este checkpoint; se conservan correos ficticios y metadata de jobs para revisión.

- `.env.docker.example`
- `apps/api/.env.example`
- `apps/api/app/Events/TechnicalRealtimeProbe.php`
- `apps/api/app/Http/Controllers/ReadinessController.php`
- `apps/api/app/Http/Controllers/TechnicalBroadcastAuthController.php`
- `apps/api/app/Http/Middleware/TechnicalAuthentication.php`
- `apps/api/app/Infrastructure/DependencyHealth.php`
- `apps/api/app/Infrastructure/ReverbHealth.php`
- `apps/api/app/Infrastructure/TechnicalAccess.php`
- `apps/api/app/Jobs/TechnicalHorizonProbe.php`
- `apps/api/app/Providers/HorizonServiceProvider.php`
- `apps/api/bootstrap/app.php`
- `apps/api/bootstrap/providers.php`
- `apps/api/composer.json`
- `apps/api/composer.lock`
- `apps/api/config/broadcasting.php`
- `apps/api/config/database.php`
- `apps/api/config/filesystems.php`
- `apps/api/config/horizon.php`
- `apps/api/config/reverb.php`
- `apps/api/config/technical.php`
- `apps/api/README.md`
- `apps/api/routes/technical.php`
- `apps/api/tests/Feature/HorizonAccessTest.php`
- `apps/api/tests/Feature/ReadinessTest.php`
- `apps/api/tests/Feature/TechnicalBroadcastAuthTest.php`
- `apps/api/tests/Support/reverb-health.php`
- `apps/api/tests/Support/verify-infrastructure.php`
- `compose.yaml`
- `docs/README.md`
- `docs/architecture/decisions/ADR-003-local-infrastructure.md`
- `docs/architecture/decisions/ADR-005-laravel-components.md`
- `docs/architecture/decisions/pending-decisions.md`
- `docs/installation/docker.md`
- `docs/installation/environment.md`
- `docs/sprints/sprint-00/backlog.md`
- `docs/sprints/sprint-00/checkpoint-00c-evidence.md`
- `docs/sprints/sprint-00/README.md`
- `infrastructure/docker/minio/Dockerfile`
- `infrastructure/docker/minio/initialize.sh`
- `infrastructure/docker/minio/policy.json`
- `infrastructure/docker/nginx/default.conf`
- `infrastructure/docker/Verify-HealthFailures.ps1`
- `README.md`

Paquetes añadidos o modificados (lockfile):

- `aws/aws-crt-php` **v1.2.7**
- `aws/aws-sdk-php` **3.399.1**
- `clue/redis-protocol` **v0.3.2**
- `clue/redis-react` **v2.8.0**
- `evenement/evenement` **v3.0.2**
- `guzzlehttp/guzzle` **7.15.5**
- `guzzlehttp/promises` **2.5.3**
- `guzzlehttp/psr7` **2.13.1**
- `laravel/horizon` **v5.50.0**
- `laravel/reverb` **v1.12.0**
- `laravel/sentinel` **v1.1.0**
- `league/flysystem-aws-s3-v3` **3.35.3**
- `mtdowling/jmespath.php` **2.9.2**
- `pusher/pusher-php-server` **7.3.0**
- `ralouphie/getallheaders` **3.0.3**
- `ratchet/rfc6455` **v0.4.1**
- `react/cache` **v1.2.0**
- `react/dns` **v1.14.0**
- `react/event-loop` **v1.6.0**
- `react/promise` **v3.3.0**
- `react/promise-timer` **v1.11.0**
- `react/socket` **v1.17.0**
- `react/stream` **v1.4.0**
- `symfony/filesystem` **v8.1.6**
- `symfony/polyfill-php83` **v1.43.0**
