# Checkpoint 00D — implementación y evidencia

Fecha: 3 de octubre de 2026. S00-010 y S00-011 implementados y validados. Aprobado explícitamente por el usuario, quien autorizó 00E el 3 de octubre de 2026. No se creó commit, no se hizo push ni se inició 00E.

## Autorización y trazabilidad

El usuario aprobó expresamente el cierre de 00C y el inicio de 00D: Platform, Shared, generador modular, API base, Sanctum y OpenAPI; conservar todo sin commit ni push. También aprobó actualizar exclusivamente las dependencias afectadas por los avisos de seguridad y repetir las validaciones de infraestructura/contratos.

Fuentes: maestro v1.6 §§58–60, AGENTS.md, ADR-001/002/005 y S00-010/S00-011. El maestro se leyó desde DOCX en modo compartido porque estaba abierto en otra aplicación; no se modificó. La respuesta singular conserva `data: {type, id, attributes}` y `meta.correlation_id`; las colecciones usan cursor y los errores detalles por campo.

00C queda aprobado, con sus resultados históricos en [su evidencia](checkpoint-00c-evidence.md). HEAD y origin/main continúan en `a96ac04400bfe9d8d24ff142e537059dca813c48`; índice Git vacío. El árbol conserva los cambios no publicados de 00C más este incremento.

## Implementación

Platform contiene solamente las capas necesarias. Application define `DependencyProbe`, el caso técnico `Readiness` y el puerto `ModuleGenerator`. Infrastructure adapta las dependencias reales y genera scaffolds. Interfaces contiene el controlador de readiness y el comando `make:module`. El provider registra puertos/comando y usa el autoload PSR-4 existente. Domain no se crea mientras no haya reglas que lo justifiquen.

El generador acepta únicamente la lista arquitectónica de doce módulos; reserva el directorio, rechaza nombres/rutas ajenos, módulos existentes y enlaces, escribe sin sobrescribir y limpia solo archivos propios si falla. Genera responsabilidades por capa y provider válido; su registro sigue siendo explícito. Las pruebas usan una raíz aleatoria temporal, prueban dos módulos, nombres rechazados, repetición y preservación del destino de un enlace. El árbol real contiene exclusivamente Platform; no existen los otros once módulos.

Shared contiene exclusivamente soporte HTTP reutilizable: respuesta singular/colección/error, renderer de excepciones y correlación por solicitud. No contiene dinero, estados, entidades ni reglas verticales; no puede importarse desde Domain/Application. El ULID de correlación se genera en el servidor y se devuelve también como `X-Correlation-ID`; no se confía en la entrada del cliente. No hay propagación a jobs ni logging estructurado anticipado.

Readiness conserva estados/códigos HTTP de 00C y ahora coloca `status`/`checks` en `data.attributes`, según el maestro. El script de fallas y las pruebas fueron adaptados. La firma técnica Reverb conserva el protocolo `{auth}` y la credencial/canal ya aprobados en 00C; sus errores usan el contrato común. El grupo versionado está bajo `/api/v1`.

Sanctum 4.3.3 configura hosts stateful locales, guard web y middleware cookie/CSRF para API. GET `/sanctum/csrf-cookie` es la ruta nativa del paquete. Las pruebas verifican cookies, acceso anónimo denegado y CSRF real (419 sin token, 204 con token), evitando el bypass habitual de testing. No se crean usuarios, login, OTP, MFA, tablas de tokens, emisión de tokens ni permisos comerciales.

[OpenAPI 3.0.3](../../api/openapi.yaml) describe dos rutas técnicas versionadas y la ruta CSRF nativa. El contrato se escribe en sintaxis JSON compatible con YAML 1.2. El esquema oficial fijado, con licencia/procedencia, se conserva como fixture. El lint usa el validador Draft-04 ya incluido en Composer 2.8.12; no agrega dependencias ni necesita red. Contract tests verifican referencias, operaciones, correspondencia exacta de rutas y cuerpos reales. El contrato no anuncia funcionalidades comerciales.

## Dependencias y seguridad

| Paquete | Al terminar 00C | Al terminar 00D |
|---|---|---|
| laravel/sanctum | No instalado | 4.3.3 |
| laravel/framework | 13.26.1 | 13.34.0 |
| league/commonmark | 2.10.0 | 2.10.3 |
| league/flysystem | 3.35.2 | 3.36.0 |

La instalación de Sanctum añadió un paquete sin actualizar otros. La actualización de seguridad posterior modificó exactamente tres paquetes, sin añadir ni eliminar dependencias transitivas. Se usó `--with-all-dependencies --minimal-changes`. Horizon 5.50.0, Reverb 1.12.0 y Flysystem S3 3.35.3 se conservan; PHP 8.4.24 y Composer 2.8.12 también.

Composer detectó inicialmente cuatro avisos: Laravel debug XSS (`GHSA-jh5r-qr3c-85q8`), CommonMark HTML y complejidad de tablas (`GHSA-97jj-33gv-5xf9`, `GHSA-3q6v-r5mr-hxv8`) y normalización de paths Flysystem (`GHSA-cxf4-7mrp-vvpr`). La actualización explícitamente aprobada deja `composer audit --locked --format=json` con `advisories: []` y `abandoned: []`.

Composer necesitó conexión temporal a `traepe_host` y se desconectó siempre en `finally`. API conserva únicamente `traepe_internal` al terminar. Los secretos permanecen ignorados; la comprobación comparó valores reales locales contra archivos modificados/nuevos sin imprimirlos y no encontró coincidencias. No se añadieron secretos a imágenes ni fixtures.

## Archivos de este incremento

| Área | Archivos |
|---|---|
| Platform Application | `app/Modules/Platform/Application/Health/{DependencyProbe,Readiness}.php`; `Application/Scaffolding/ModuleGenerator.php` |
| Platform Infrastructure | `Infrastructure/Health/{DependencyHealth,ReverbHealth}.php`; `Infrastructure/Scaffolding/ModuleScaffolder.php`; `Infrastructure/PlatformServiceProvider.php` |
| Platform Interfaces | `Interfaces/Console/MakeModule.php`; `Interfaces/Http/ReadinessController.php` |
| Shared | `app/Shared/Http/{ApiResponse,ApiExceptionRenderer,CorrelationId}.php` |
| Arranque y autenticación | `bootstrap/app.php`, `bootstrap/providers.php`, `routes/technical.php`, `config/sanctum.php`, `app/Http/Middleware/TechnicalAuthentication.php` |
| Dependencias/entorno | `composer.json`, `composer.lock`, `apps/api/.env.example`, `.env.docker.example`, `compose.yaml`, `infrastructure/docker/php/Dockerfile` |
| Pruebas nuevas | `tests/Feature/{ApiContract,Architecture,ModuleScaffolding,OpenApiContract,SanctumFoundation}Test.php`; `tests/Support/lint-openapi.php` |
| Pruebas adaptadas | `tests/Feature/ReadinessTest.php`, `tests/Support/reverb-health.php`, `infrastructure/docker/Verify-HealthFailures.ps1` |
| Contratos/documentación | `docs/api/{README.md,openapi.yaml,schemas/*}`, `docs/architecture/README.md`, ADR-005, README raíz/API, guía Docker, índice docs, backlog/README Sprint 00, evidencias 00C/00D |

Las rutas `app/...`, `bootstrap/...`, `config/...` y `tests/...` de la tabla pertenecen a `apps/api`. DependencyHealth/ReverbHealth y ReadinessController de 00C se trasladaron a Platform, con imports/probe ajustados; no quedan copias antiguas. El resto de infraestructura, jobs/eventos y acceso técnico de 00C se conserva. No se modifican archivos del maestro, datos comerciales ni migraciones existentes.

## Validaciones ejecutadas

| Validación | Resultado |
|---|---|
| `php artisan test` en Compose | **19 pruebas, 590 assertions; todas pasan** |
| Misma suite en imagen sin red ni montajes | **19 pruebas, 590 assertions; todas pasan** |
| Pint `--test` | 62 archivos, PASS |
| Sintaxis nativa `php -l` | 63 archivos, PASS |
| `composer validate --strict` | PASS |
| `composer audit --locked` con acceso al registro | Sin avisos ni paquetes abandonados |
| `lint-openapi.php` | Esquema oficial acepta contrato y rechaza documento malformado; PASS también sin red/montajes |
| Build Docker de API | Lock instalado desde cero en primera construcción 00D; reconstrucción final correcta |
| `compose config --quiet` / `git diff --check` | PASS |
| Stack final | 8 servicios healthy; minio-init termina 0 |
| HTTP real sin header Accept | Readiness 200/ready y contrato/correlación; ruta ausente 404/not_found |
| HTTP CSRF real | 204; no se imprimen cookies |
| Probe smoke | PostGIS, cache Redis, S3 escribir/leer/borrar, acceso anónimo denegado, credencial S3 limitada y SMTP capturado; PASS |
| Probe queue / failed-job | Consumo real, reintento controlado, redelivery idempotente, fallo terminal visible y retry explícito procesado; PASS |
| Probe websocket | Handshake real, firma inválida rechazada, autorización HTTP, suscripción privada, evento versionado y reconexión; PASS |
| Probe migrations | Base temporal aislada, dos ejecuciones y limpieza únicamente de esa base; PASS |
| Fallas controladas | PostgreSQL, Redis, MinIO, Horizon, Reverb y Mailpit: readiness 503 correcto, liveness 200; recuperación completa y productor detecta Redis caído |
| Persistencia tras restart | PostgreSQL, Redis, MinIO y Mailpit conservan marcadores; probe limpia sus objetos/cache |
| Secretos / alcance negativo | Sin coincidencias de credenciales reales; solo Platform; sin módulos comerciales, commit ni push |

Imagen final: `traepe/php-fpm:8.4.24-00d`, índice `sha256:ede7bcaad85f204c8720df0faaae56ab8fcef32de43f8253d6e067131382f3e6`. API, Horizon y Reverb fueron recreados con ella. MinIO conserva la imagen aprobada de 00C. El montaje `docs/api` es de solo lectura; el Dockerfile lo incluye para validar sin checkout montado.

La primera prueba adicional en imagen aislada falló por una clave ficticia de longitud incorrecta en el comando y ausencia de `.env` de pruebas. Se corrigió el harness: clave ficticia de exactamente 32 bytes y copia de `.env.example` únicamente dentro del contenedor efímero. No se modificaron credenciales locales ni se incorporó `.env` al build. La repetición terminó sin fallos ni warnings.

## Riesgos y límites pendientes

- El payload de readiness cambia de ubicación para cumplir el maestro; cualquier consumidor técnico anterior debe migrar a `data.attributes`. Los probes del repositorio ya lo hacen.
- Sanctum es una fundación técnica: futuros flujos requieren especificación de identidad, autorización por alcance, revocación y políticas; este checkpoint no las decide.
- Correlación hacia jobs/logs, idempotencia y outbox/inbox corresponden a 00E; Pest/Larastan/PHPStan y CI a 00F. No se instalaron ni implementaron anticipadamente. La validación disponible aquí es PHPUnit/Pint, sintaxis nativa, límites arquitectónicos, Composer y contratos.
- MinIO upstream archivado permanece exclusivamente local, conforme a DP-024; no selecciona proveedor productivo. No hay despliegue ni pruebas productivas.
- Las decisiones de negocio abiertas y DP-012/DP-013B/DP-022 conservan su bloqueo fuera de este alcance.

No se identificó una nueva decisión bloqueante para S00-010/S00-011. El siguiente paso requiere revisar/aprobar este checkpoint; no se avanza a 00E automáticamente.
