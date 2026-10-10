# trae.pe API

Backend maestro y API pura de trae.pe, construido con Laravel 13 y PHP 8.4.

## Estado

Sprint 00 prepara exclusivamente la fundación técnica: Platform, infraestructura, contratos, observabilidad y entrega. No incluye identidad funcional, frontend ni dominios comerciales.

00C está aprobado. 00D agrega Platform/Shared, `make:module`, respuestas/errores comunes, correlación HTTP, Sanctum 4.3.3 y [OpenAPI](../../docs/api/openapi.yaml). Laravel 13.34.0, CommonMark 2.10.3 y Flysystem 3.36.0 corrigen los avisos detectados con actualización explícitamente aprobada. No hay flujos de identidad ni endpoints comerciales. Véase [evidencia de 00D](../../docs/sprints/sprint-00/checkpoint-00d-evidence.md).

## Ejecución local

00E incorpora correlación HTTP/jobs/eventos, logs JSON redactados y primitivas PostgreSQL de idempotencia/outbox/inbox. El productor y consumidor son exclusivamente ficticios. Véase [evidencia de 00E](../../docs/sprints/sprint-00/checkpoint-00e-evidence.md).

00C: Horizon 5.50.0, Reverb 1.12.0 y Flysystem S3 3.35.3 operativos. Mailpit recibe SMTP local; UI `http://127.0.0.1:11825`. Horizon requiere usuario `technical` y contraseña local ignorada. Reverb autoriza solo el canal privado técnico v1; MinIO usa un bucket privado con credencial limitada. `/up` es liveness; `/api/v1/health/ready` es readiness sanitizada. Véase [evidencia](../../docs/sprints/sprint-00/checkpoint-00c-evidence.md).

Desde la raíz del repositorio, sigue la [guía Docker](../../docs/installation/docker.md). Composer y Artisan se ejecutan dentro del contenedor PHP:

```powershell
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec api composer install
docker compose --env-file .env.docker exec api php artisan migrate
docker compose --env-file .env.docker exec api php artisan test
```

La API queda disponible en `http://localhost:8000`. XAMPP, Apache, MySQL y el PHP instalado en Windows no son necesarios para trae.pe.


00E está aprobado. 00F incorpora Pest 4.7.8, Larastan 3.12.2/PHPStan 2.2.16 y Pint con comandos Composer y CI aislada. La [guía de instalación](../../docs/installation/README.md) documenta instalación nueva, calidad, recuperación segura y reproducción del pipeline. La validación remota de GitHub y la aprobación final siguen pendientes.

## Estado posterior — 01A, 10 de octubre de 2026

Sprint 00 está cerrado con aprobación explícita, revisión independiente y CI verde. [Identity local](../../docs/sprints/sprint-01/checkpoint-01a-evidence.md) incorpora OTP, consentimiento ficticio local-v1 y sesión Sanctum; [runbook](../../docs/sprints/sprint-01/local-runbook.md). Los párrafos de checkpoints anteriores conservan el alcance histórico. 01A no incluye frontend ni proveedores productivos, no agrega dependencias y aún no está publicado.

## Estado posterior — 02A, 10 de octubre de 2026

Identity local 01A–01F está aprobado/publicado; [CI de 01F](https://github.com/nromero96/traepe/actions/runs/38048298696) pasó para `3868484`. Marketplace inicia un [ejercicio de cobertura ficticia](../../docs/sprints/sprint-02/README.md) aprobado conforme a DP-027, con PostGIS existente y diagnóstico por consola. No agrega rutas ni datos comerciales. [Operación y evidencia 02A](../../docs/sprints/sprint-02/checkpoint-02a-evidence.md).

## Estado posterior — 02B, 10 de octubre de 2026

02A se publicó en `edc1b0f` con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38070750987). 02B incorpora GET `/api/v1/marketplace/local-coverage-probe` en local/testing, con validación, bloqueo de otros entornos y contrato OpenAPI 1.3.0; reutiliza los polígonos y política aprobados. Es una lectura pública de fixtures sin sesión ni datos comerciales. [Operación y evidencia de 02B](../../docs/sprints/sprint-02/checkpoint-02b-evidence.md).

## Estado posterior — 02C, 10 de octubre de 2026

02B se publicó en `b4b81f7` con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38075664329). DP-028 y [ADR-006](../../docs/architecture/decisions/ADR-006-marketplace-geographic-foundation.md) aprueban la base geográfica vacía de Marketplace: countries, markets y service_zones, mercados/zonas solo draft y zonas solo fixture, PostGIS geography(MultiPolygon,4326). La migración está aplicada localmente; no hay seeds ni activación y las sondas siguen usando los fixtures inline. [Plan aprobado](../../docs/sprints/sprint-02/checkpoint-02c-plan.md) y [evidencia de 02C](../../docs/sprints/sprint-02/checkpoint-02c-evidence.md): 123 pruebas y 2766 aserciones correctas. El usuario aprobó el checkpoint y autorizó commit/push/CI; la publicación se ejecuta bajo esa autorización. DP-001 continúa abierta.

## Estado posterior — 02D, 10 de octubre de 2026

02C se publicó en `720f0fa` con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38078507497). DP-029 aprueba el [diagnóstico persistido por mercado](../../docs/sprints/sprint-02/checkpoint-02d-plan.md): `marketplace:local-persisted-coverage {market_public_id} {longitude} {latitude}`, exclusivamente local/testing y de lectura, sobre mercados draft y sus zonas draft/fixture. Geography nativa con bordes incluidos, prioridad/empate de DP-027, sin seeds ni activación. Las sondas existentes conservan local-coverage-v1; el comando nuevo devuelve local-persisted-coverage-v1. [Evidencia y operación de 02D](../../docs/sprints/sprint-02/checkpoint-02d-evidence.md): 137 pruebas y 2974 aserciones correctas; tres tablas locales vacías. El usuario aprobó el checkpoint y autorizó commit/push/CI; la publicación se ejecuta bajo esa autorización. DP-001 sigue abierta.
