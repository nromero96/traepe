# Checkpoint 00E — Evidencia técnica

Fecha: 3 de octubre de 2026. Alcance autorizado: S00-012 y S00-013, después de la aprobación explícita de 00D. Checkpoint aprobado explícitamente el 3 de octubre de 2026; 00F autorizado. Sin commit, push, nuevas dependencias ni funcionalidades comerciales.

## Trazabilidad y comportamiento

Documento maestro v1.6, secciones 45, 55 y 58–64; ADR-002; backlog y criterios de Sprint 00. Platform conserva reglas y casos técnicos de entrega; Shared contiene solo correlación y observabilidad transversales. Domain/Application no dependen del framework. No se materializaron otros módulos.

- Un ULID válido en `X-Correlation-ID` se conserva; una entrada inválida se sustituye. Respuesta, contexto, jobs, evento y efecto técnico comparten correlación. La correlación no concede permisos. El contexto se limpia entre solicitudes/jobs.
- Logging JSON con vocabulario y campos permitidos; elimina mensajes libres, datos personales, headers, cookies, URL, payload y detalles de excepción. Nginx conserva estado/duración/correlación sin URI ni IP. Su error log `emerg` reduce diagnóstico crudo para evitar datos del request.
- Idempotencia por scope/actor/key almacenados como hashes, fingerprint canónico y respuesta por referencia ULID. Misma entrada devuelve la referencia original; distinto contenido o clave vencida se rechaza. Expiración explícita, sin política automática de purga/reutilización.
- Una transacción PostgreSQL guarda claim, cambio ficticio y outbox cifrado. Un fallo revierte los tres. No se hacen efectos externos en el callback del productor.
- Eventos v1 validados e inmutables tanto en memoria como mediante trigger PostgreSQL. Solo los metadatos de entrega pueden cambiar; DELETE del outbox se rechaza.
- El comando `platform:dispatch-outbox` encola un lote en Redis/Horizon. El dispatcher usa `FOR UPDATE SKIP LOCKED`, entrega al menos una vez y conserva pendientes al fallar, con espera limitada de 1–60 segundos. Redis recibe solo la referencia al evento.
- Inbox y efecto se guardan juntos, con unicidad de event_id y de source/message_id. Un mensaje duplicado produce un único efecto observable. No se promete entrega exactamente una vez.

## Archivos modificados en 00E

El árbol también conserva cambios autorizados de 00C/00D aún sin commit; esta lista distingue el bloque actual.

- `apps/api/app/Shared/Http/CorrelationId.php`, `Shared/Observability/{SafeLogProcessor,ConfigureSafeLogging,ReportException}.php`, `bootstrap/app.php`, `config/logging.php` y `.env.example`: correlación, redacción y reporte seguro.
- `apps/api/app/Modules/Platform/Domain/Delivery/`: fingerprint, evento y rechazos técnicos.
- `Platform/Application/Delivery/`: puertos y caso técnico productor.
- `Platform/Infrastructure/Delivery/` y `Infrastructure/PlatformServiceProvider.php`: adaptadores PostgreSQL/Redis y propagación del contexto de jobs.
- `Platform/Interfaces/Jobs/{ConsumeTechnicalEvent,PublishTechnicalOutbox}.php` y `Interfaces/Console/DispatchTechnicalOutbox.php`: entrada técnica de entrega.
- `apps/api/database/migrations/2026_10_03_000000_create_platform_delivery_primitives.php`: cinco tablas técnicas, dominios ULID/hash, índices, restricciones y trigger de inmutabilidad; migración aditiva aplicada al entorno local.
- `apps/api/phpunit.xml`, pruebas de observabilidad, fingerprint, evento y comando; `tests/Integration/`, `tests/Support/{delivery-race,verify-delivery,lint-technical-event}.php`: pruebas aisladas y reales.
- `compose.yaml`, `infrastructure/docker/php/{Dockerfile,runtime-entrypoint.sh}`, `nginx/default.conf`, `Verify-CorrelationConcurrency.ps1`: canal seguro, permisos de logs y comprobación concurrente.
- `docs/api/openapi.yaml`, `schemas/platform-technical-probe.v1.json` y sus README: contrato de correlación/evento.
- README de raíz/API/documentación, arquitectura, seguridad, eventos, instalación Docker, ADR-002 y control del Sprint 00: estado, política y runbook.

## Validaciones

| Validación | Resultado |
|---|---|
| Unit + Feature + PostgreSQL Integration real | 33 pruebas, 821 assertions |
| Imagen final sin mounts ni red, Unit + Feature | 26 pruebas, 754 assertions |
| Pint | 95 archivos, correcto |
| Sintaxis PHP | 96 archivos, correcto |
| OpenAPI oficial Draft 4 y documento inválido | Correcto / rechazo esperado |
| Evento cifrado persistido contra esquema v1 y evento inválido | Correcto / rechazo esperado |
| Horizon/Redis real, replay y redelivery | Un evento aplicado una sola vez; correlación compartida |
| 16 solicitudes HTTP concurrentes | IDs independientes en respuesta y logs |
| Canarios en query/header/cookie | Excluidos de logs; 404 JSON correlacionado presente |
| PostGIS, Redis, S3 privado/restringido y SMTP Mailpit | Correcto |
| Migración aplicada y reaplicada | Correcto; segunda ejecución sin cambios |
| Rollback/reaplicación de migración | Solo en bases temporales propias, correcto |
| Cambios/DELETE del evento, hash diferente, expiry y ULID inválido | Rechazos esperados |
| Carreras de productores, dispatchers y consumidores independientes | Una operación/publicación/efecto |
| Docker Compose | Ocho servicios healthy; MinIO init exit 0 |
| Diff, staging y secretos | Sin errores de espacios, staging vacío, sin secretos locales encontrados |

Imagen ejecutable: `traepe/php-fpm:8.4.24-00e`, digest `sha256:2ea6ddedf0bf21822dc489407c331ad0b2bc3fc1f401f7dad2928c2c77ce8b74`. API conectada únicamente a `traepe_internal`. HEAD y origin/main permanecen en `a96ac04400bfe9d8d24ff142e537059dca813c48`.

Se corrigieron dos problemas encontrados durante validación: comparar vencimiento del lote con hora SQL evita truncamiento de microsegundos; grupo/permisos de logs permiten compartir escritura CLI/Horizon/FPM. Los logs de publicación/reintento conservan la correlación individual del evento, incluso en lotes de varias solicitudes.

## Límites y riesgos pendientes

- Son primitivas y datos ficticios; no hay mutaciones comerciales, usuarios, pagos ni nuevos endpoints de negocio.
- Las pruebas reales conservan filas técnicas para revisión. Las pruebas de integración eliminan solo su propia base temporal; necesitan privilegios locales de creación de bases.
- La entrega admite duplicados; inbox protege el efecto técnico. Consumidores futuros necesitan su transacción, autorización y contrato de dominio.
- APP_KEY protege el contenido del outbox. Retención, rotación de claves y cadencia de publicación productivas requieren diseño posterior. El lote es explícito; los pendientes deben volver a despacharse después del plazo.
- La referencia de respuesta es técnica; los futuros casos deben definir su contrato y scope/actor/expiry. No se inventó una política comercial.
- PHPStan/Larastan, Pest y GitHub Actions corresponden a 00F y no se ejecutaron ni instalaron aquí.
- MinIO sigue restringido al desarrollo conforme a DP-024. Los pendientes productivos existentes continúan abiertos.

Véase [runbook local](../../installation/docker.md#checkpoint-00e-correlación-y-entrega-técnica). La aprobación explícita de este checkpoint autorizó iniciar 00F.
