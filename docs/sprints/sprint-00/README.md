# Sprint 00 — Fundación técnica ejecutable

## Objetivo

Construir una base local y de CI reproducible sobre la cual puedan implementarse después los dominios del MVP, sin incorporar todavía reglas comerciales ni funcionalidades verticales.

La [preparación técnica documental](../preparation/README.md) ya finalizó y no consume capacidad de este sprint.

## Resultado comprometido

Al finalizar Sprint 00 debe existir:

- Laravel 13 con PHP 8.4 funcional en `apps/api`, conforme a ADR-001.
- Entorno reproducible con Docker Compose.
- PostgreSQL con PostGIS y Redis.
- Laravel Horizon y Laravel Reverb operativos.
- almacenamiento compatible con S3 mediante MinIO local.
- correo local mediante Mailpit.
- módulo técnico `Platform` materializado en `apps/api/app/Modules/Platform`.
- `apps/api/app/Shared` materializado y restringido a elementos realmente transversales.
- plantilla o comando generador probado para crear módulos futuros sin dejar carpetas vacías.
- lista arquitectónica conservada para Identity, Marketplace, Catalog, Pricing, Ordering, Payments, Logistics, Settlements, Support, Engagement, Platform y DataAI; cada módulo adicional se crea al comenzar su implementación.
- configuración segura de entorno y plantilla sin secretos.
- API base `/api/v1`, formato común de respuestas/errores y OpenAPI inicial.
- Laravel Sanctum configurado como base técnica, sin flujos ni reglas de identidad todavía.
- `correlation_id`, logs estructurados, base de idempotencia y patrón técnico outbox/inbox.
- health checks de aplicación y dependencias.
- Pest, Pint y Larastan/PHPStan configurados.
- pipeline de GitHub Actions.
- documentación comprobada de instalación/operación local.
- pruebas de infraestructura y checkpoint final aprobado.

Las tecnologías enumeradas están aprobadas en ADR-001 a ADR-005. CD, hosting, frontend y proveedores productivos continúan en [decisiones pendientes](../../architecture/decisions/pending-decisions.md).

## Fuera de alcance

- Catálogo, inventario y discovery.
- Carritos, pedidos y máquinas de estado comerciales.
- Pricing, promociones, pagos o reembolsos.
- Logística, tracking y asignación.
- Dashboards o interfaces web.
- Integraciones productivas y datos reales.
- Reglas específicas de Tienda Siete o cualquier vertical.
- CD, hosting o despliegue productivo.

Platform y Shared se crearán como límites estructurales comprobables en 00D; los demás módulos permanecen solo documentados. Ninguno contendrá casos de uso ni reglas de negocio en este sprint.

## Orden de ejecución

1. Aprobar decisiones técnicas bloqueantes.
2. Instalar Laravel y establecer configuración segura.
3. Construir Docker Compose y servicios locales.
4. Incorporar Platform, Shared, generador modular y contratos base.
5. Añadir observabilidad, idempotencia, outbox/inbox y health checks.
6. Configurar calidad, OpenAPI y CI.
7. Completar documentación, pruebas de infraestructura y checkpoint final.

## Checkpoints obligatorios

| Checkpoint | Tareas | Salida requerida |
|---|---|---|
| 00A | S00-001 a S00-003 | Decisiones, framework base y entorno seguro. |
| 00B | S00-004 y S00-005 | Stack local, PostgreSQL/PostGIS y Redis. |
| 00C | S00-006 a S00-009 | Horizon, Reverb, MinIO, Mailpit y health checks. |
| 00D | S00-010 y S00-011 | Platform/Shared/generador, API base, Sanctum y OpenAPI. |
| 00E | S00-012 y S00-013 | Correlación/logs e idempotencia/outbox/inbox. |
| 00F | S00-014 a S00-016 | Quality tooling, CI, documentación y cierre. |

Cada checkpoint exige ejecutar sus pruebas, revisar el diff completo, resumir archivos modificados, registrar riesgos pendientes y obtener aprobación explícita antes de iniciar el siguiente.

El orden detallado y las dependencias están en el [backlog](backlog.md).

## Documentos de control

- [Backlog técnico](backlog.md)
- [Criterios de aceptación](acceptance-criteria.md)
- [Definición de terminado](definition-of-done.md)
- [Decisiones pendientes](../../architecture/decisions/pending-decisions.md)

## Estado de checkpoints

Checkpoint 00A está aprobado y cerrado. S00-004 y S00-005 de 00B están implementadas y verificadas; no iniciar 00C hasta presentar pruebas, diff, archivos y riesgos de 00B y recibir aprobación explícita. DP-012, DP-013B y proveedores productivos permanecen fuera del alcance ejecutable.
