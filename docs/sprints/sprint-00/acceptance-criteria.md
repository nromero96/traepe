# Criterios de aceptación — Sprint 00

Sprint 00 solo se acepta cuando todas las puertas siguientes tienen evidencia verificable.

## 1. Decisiones y alcance

- ADR-001 a ADR-005 registran las decisiones técnicas aprobadas.
- Laravel 13 y PHP 8.4 son compatibles con las dependencias bloqueadas.
- DP-013B (CD/hosting) permanece separado y fuera del sprint.
- No existen implementaciones de catálogo, pedidos, pricing, pagos, logística, dashboards o reglas comerciales.

## 2. Aplicación y arquitectura

- La versión aprobada de Laravel funciona en `apps/api` desde un checkout limpio.
- Solo `Platform` y `app/Shared` están materializados; los demás módulos continúan documentados hasta comenzar su implementación.
- Una plantilla o comando generador crea estructuras modulares válidas sin mantener carpetas vacías.
- Pruebas automatizadas verifican autoload, generador y dirección de dependencias.
- No hay reglas verticales, tablas privadas ajenas ni controladores con lógica de negocio.

## 3. Entorno reproducible

- Docker Compose levanta API, PostgreSQL/PostGIS, Redis, MinIO y Mailpit.
- Horizon y Reverb funcionan con acceso/configuración segura.
- Versiones de imágenes y dependencias están fijadas de forma reproducible.
- Arranque, reinicio y diagnóstico están documentados y probados.

## 4. Seguridad de configuración

- No existen secretos, tokens, PII ni credenciales productivas versionadas o registradas en logs.
- `.env.example` documenta variables sin valores peligrosos.
- Dashboards técnicos y canales privados exigen autorización apropiada.
- Producción no hereda defaults locales inseguros.

## 5. Contratos y plataforma

- `/api/v1` responde con formato estándar de éxito y error.
- Sanctum está configurado como base técnica sin implementar flujos de identidad o permisos comerciales.
- Errores tienen código estable y `correlation_id`; excepciones internas están sanitizadas.
- OpenAPI inicial valida y refleja únicamente endpoints técnicos del sprint.
- Correlation ID se propaga a logs y jobs; logs son JSON estructurado.
- Base de idempotencia detecta repetición y mismatch concurrente.
- Outbox conserva `event_id` y payload inmutables; solo sus metadatos de publicación pueden actualizarse.
- Inbox aplica un constraint único por `event_id`; el consumidor técnico deduplica reintentos sin duplicar el efecto observable.
- No existen consumidores comerciales.

## 6. Infraestructura verificable

- PostGIS y Redis tienen pruebas de integración.
- MinIO supera escritura, lectura y borrado técnico; Mailpit recibe correo local.
- La cola utiliza entrega al menos una vez. Los jobs y consumidores deben ser idempotentes para que los reintentos no dupliquen efectos.
- Reverb autoriza un canal privado y transmite un evento técnico.
- Health checks distinguen liveness/readiness y fallas de dependencias sin filtrar infraestructura sensible.

## 7. Calidad y CI

- Pest, Pint y Larastan/PHPStan ejecutan con comandos documentados y código de salida fiable.
- Suite incluye éxito, rechazo, límites, concurrencia e integración donde corresponde.
- GitHub Actions reproduce migraciones, tests, formato, análisis, OpenAPI y validación Compose.
- Pipeline usa permisos mínimos y finaliza verde desde checkout limpio.

## 8. Documentación y checkpoints

- Una persona nueva instala y valida el stack siguiendo solo la documentación.
- README y runbook incluyen prerrequisitos, comandos, troubleshooting y reset seguro.
- No hay carpetas ceremoniales vacías; cada estructura creada tiene propósito y prueba.
- Todas las tareas S00-001 a S00-016 cumplen su aceptación y están `DONE`.
- Los checkpoints 00A a 00F ejecutan sus pruebas, revisan el diff, resumen archivos y riesgos pendientes y obtienen aprobación antes de continuar.
- El checkpoint final registra validaciones, archivos, riesgos residuales y aprobación explícita.
