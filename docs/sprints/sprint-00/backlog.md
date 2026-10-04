# Backlog técnico detallado — Sprint 00

## Convenciones

- Orden obligatorio salvo paralelización indicada por dependencias.
- Estados: `BLOCKED_APPROVAL`, `READY`, `IN_PROGRESS`, `DONE`.
- Checkpoint 00A está aprobado y cerrado. Checkpoint 00B fue autorizado y publicado el 3 de octubre de 2026. 00C fue aprobado explícitamente y 00D autorizado el 3 de octubre de 2026. 00D fue aprobado explícitamente y 00E aprobado y 00C–00F publicados con autorización explícita el 3 de octubre de 2026. DP-023 y DP-024 están resueltas.
- Las actividades PRE-001 a PRE-004 están cerradas fuera del sprint en [Preparación técnica](../preparation/README.md).

## Mapa de dependencias

```text
S00-001
  ├─ S00-002 ─ S00-003 ─┬─ S00-004 ─┬─ S00-006 ─ S00-009
  │                      │           ├─ S00-007 ─ S00-009
  │                      │           └─ S00-008 ─ S00-009
  │                      └─ S00-005 ──────────── S00-009
  └─ S00-010 ─ S00-011
S00-004 + S00-005 ─ S00-012
S00-003 + S00-010 ─ S00-013
S00-009 + S00-011 + S00-012 + S00-013 ─ S00-014 ─ S00-015 ─ S00-016
```

S00-010 puede avanzar en paralelo con S00-002 a S00-009 una vez aprobadas las decisiones.

## Checkpoints y autorización de continuidad

| Checkpoint | Tareas | Condición para continuar |
|---|---|---|
| 00A | S00-001 a S00-003 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación. |
| 00B | S00-004 y S00-005 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación. |
| 00C | S00-006 a S00-009 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación. |
| 00D | S00-010 y S00-011 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación. |
| 00E | S00-012 y S00-013 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación. |
| 00F | S00-014 a S00-016 | Pruebas del bloque, revisión del diff, resumen de archivos, riesgos pendientes y aprobación final. |

No se inicia un checkpoint si el anterior no tiene aprobación explícita registrada.

## S00-001 — Aprobar decisiones técnicas

**Objetivo:** cerrar o sustituir las propuestas que condicionan la ejecución.

**Trabajo:** registrar Laravel 13/PHP 8.4, datos/identificadores/mensajería, infraestructura local, calidad/CI y componentes Laravel; mantener CD, hosting, frontend y proveedores productivos fuera de alcance.

**Archivos esperados:** ADR-001 a ADR-005 y actualización de `pending-decisions.md`.

**Dependencias:** PRE-001 a PRE-004.

**Criterios de aceptación:** ADR aprobados registran Laravel 13, PHP 8.4 y el stack autorizado; declaran que solo sustituyen decisiones técnicas; asuntos abiertos permanecen en pending-decisions.

**Pruebas:** revisión documental cruzada con maestro, arquitectura y backlog.

**Riesgos:** aprobar herramientas incompatibles o convertir una elección técnica en regla comercial.

**Estado:** `DONE`.

## S00-002 — Crear la versión aprobada de Laravel en apps/api

**Objetivo:** disponer del backend maestro ejecutable mínimo.

**Trabajo:** crear Laravel 13 en `apps/api` con PHP 8.4; conservar historial y archivos raíz; validar CLI y endpoint base sin negocio.

**Archivos esperados:** `apps/api/artisan`, `apps/api/composer.json`, estructura estándar Laravel y lockfile.

**Dependencias:** S00-001 y ADR-001.

**Criterios de aceptación:** Laravel reporta versión 13 y PHP versión 8.4; aplicación inicia; no contiene catálogo, pedidos, pagos, logística, pricing ni dashboards.

**Pruebas:** `php artisan --version`, smoke test HTTP y suite base.

**Riesgos:** incompatibilidad PHP/extensiones, scaffolding accidental de funcionalidades o sobrescritura de archivos del repositorio.

**Estado:** `DONE`.

## S00-003 — Configuración segura del entorno

**Objetivo:** separar configuración versionable de secretos y valores locales.

**Trabajo:** definir `.env.example`, validación de variables, claves locales, permisos, defaults seguros y política de secretos.

**Archivos esperados:** `apps/api/.env.example`, configuración Laravel ajustada y `docs/installation/environment.md`.

**Dependencias:** S00-002.

**Criterios de aceptación:** ningún secreto real versionado; producción no usa defaults inseguros; variables requeridas documentadas; debug desactivable por entorno.

**Pruebas:** escaneo de secretos, arranque con archivo de ejemplo completado y fallo claro ante variables obligatorias ausentes.

**Riesgos:** credenciales expuestas, configuración divergente o valores inseguros heredados.

**Estado:** `DONE`.

## S00-004 — Docker Compose y red local

**Objetivo:** ejecutar el stack de desarrollo de forma reproducible.

**Trabajo:** definir Nginx, PHP-FPM 8.4, PostgreSQL/PostGIS y Redis; red exclusiva, volúmenes nombrados, puertos locales configurables y healthchecks. MinIO y Mailpit pertenecen a 00C.

**Archivos esperados:** `compose.yaml`, archivos bajo `infrastructure/docker/`, `.dockerignore`.

**Dependencias:** S00-003; aprobación DP-015, DP-017 y DP-018.

**Criterios de aceptación:** un comando documentado levanta el stack; volúmenes persisten; servicios no exponen credenciales productivas; reinicio limpio es reproducible.

**Pruebas:** validación de Compose, arranque desde cero, health de contenedores, reinicio y conexión entre servicios.

**Riesgos:** diferencias Windows/Linux, puertos ocupados, imágenes no fijadas o datos locales difíciles de recuperar.

**Estado:** `DONE` — Checkpoint 00B autorizado y publicado el 3 de octubre de 2026.

## S00-005 — PostgreSQL, PostGIS y Redis

**Objetivo:** habilitar persistencia y servicios reconstruibles del MVP técnico.

**Trabajo:** configurar conexión PostgreSQL, extensión PostGIS, Redis para caché/colas y una migración técnica mínima de verificación sin tablas comerciales.

**Archivos esperados:** configuración DB/cache/queue, migración técnica de extensiones y pruebas de integración.

**Dependencias:** S00-003 y S00-004.

**Criterios de aceptación:** conexión estable; PostGIS disponible; Redis responde; PostgreSQL sigue siendo fuente transaccional; migración es repetible y compatible hacia adelante.

**Pruebas:** consulta de versión PostGIS, caché Redis write/read/delete, dispatch y consumo de job técnico, migraciones sobre volumen nuevo, suite en contenedor y persistencia tras reinicio. La migración idempotente de PostGIS no elimina la extensión en rollback para evitar pérdida de datos espaciales.

**Riesgos:** privilegios insuficientes, extensiones ausentes o uso accidental de Redis como fuente de verdad.

**Estado:** `DONE` — Checkpoint 00B autorizado y publicado el 3 de octubre de 2026.

## S00-006 — Horizon y base de colas

**Objetivo:** operar colas Redis con una configuración observable mínima.

**Trabajo:** instalar/configurar Horizon, colas base, timeouts, reintentos y acceso protegido al dashboard técnico.

**Archivos esperados:** dependencias/configuración Horizon, provider/policy de acceso y prueba de job técnico.

**Dependencias:** S00-005; aprobación DP-020.

**Criterios de aceptación:** la cola utiliza entrega al menos una vez. Los jobs y consumidores deben ser idempotentes para que los reintentos no dupliquen efectos; dashboard no es público; fallo/reintento queda observable; no hay jobs de negocio.

**Pruebas:** dispatch/consume, retry controlado, autorización del dashboard y conexión Redis caída.

**Riesgos:** acceso administrativo abierto, reintentos duplicados o configuración distinta entre local y CI.

**Estado:** `DONE` — implementación y pruebas de 00C verificadas; checkpoint aprobado explícitamente.

## S00-007 — Reverb y tiempo real base

**Objetivo:** verificar infraestructura WebSocket sin eventos comerciales.

**Trabajo:** instalar/configurar Reverb, credenciales por entorno, canal técnico autorizado y cliente de prueba mínimo.

**Archivos esperados:** dependencia/configuración Reverb, rutas de canales y prueba de broadcasting.

**Dependencias:** S00-004; aprobación DP-021.

**Criterios de aceptación:** conexión local autenticada; evento técnico recibido; canal privado rechaza actores no autorizados; sin datos de otro alcance.

**Pruebas:** conexión, autorización positiva/negativa, desconexión y reconexión.

**Riesgos:** secretos expuestos, canales públicos o falsa garantía de entrega.

**Estado:** `DONE` — implementación y pruebas de 00C verificadas; checkpoint aprobado explícitamente.

## S00-008 — Storage S3 local y correo local

**Objetivo:** validar adaptadores locales sin elegir proveedores productivos.

**Trabajo:** conectar Laravel a MinIO mediante contrato S3 y Mailpit mediante SMTP; documentar buckets y credenciales locales.

**Archivos esperados:** configuración filesystem/mail, inicialización MinIO y pruebas técnicas.

**Dependencias:** S00-004; aprobación DP-017 y DP-018.

**Criterios de aceptación:** escribir/leer/eliminar objeto técnico; enviar correo visible en Mailpit; ningún proveedor productivo queda implícito.

**Pruebas:** integración S3, URL firmada si se habilita, envío SMTP y recuperación tras reinicio.

**Riesgos:** diferencias entre MinIO y S3 productivo, buckets públicos o correos saliendo fuera del entorno local.

**Estado:** `DONE` — implementación y pruebas de 00C verificadas; checkpoint aprobado explícitamente.

## S00-009 — Health checks de infraestructura

**Objetivo:** exponer estado útil de aplicación y dependencias sin filtrar información sensible.

**Trabajo:** definir liveness/readiness para app, PostgreSQL, Redis, storage, colas y servicios relevantes; separar degradación de caída total.

**Archivos esperados:** endpoint/controlador técnico, servicios de health y pruebas.

**Dependencias:** S00-005 a S00-008.

**Criterios de aceptación:** liveness no depende innecesariamente de terceros; readiness detecta dependencias críticas; respuestas no revelan credenciales, hosts internos ni trazas.

**Pruebas:** estado sano y fallas simuladas por dependencia; códigos HTTP y payload estables.

**Riesgos:** healthchecks costosos, falsos positivos o exposición de infraestructura.

**Estado:** `DONE` — implementación y pruebas de 00C verificadas; checkpoint aprobado explícitamente.

## S00-010 — Platform, Shared y generador modular

**Objetivo:** materializar únicamente la base transversal necesaria y un mecanismo repetible para futuros módulos.

**Trabajo:** crear `Platform` con las capas necesarias, crear `app/Shared` solo con elementos transversales reales y preparar una plantilla o comando generador probado para módulos futuros. Mantener los doce módulos objetivo solo en documentación hasta que comience la implementación de cada uno.

**Archivos esperados:** `apps/api/app/Modules/Platform`, `apps/api/app/Shared`, plantilla o comando generador, configuración de autoload/providers y pruebas de arquitectura/generador.

**Dependencias:** S00-001 y S00-002; aprobación DP-011 para identificadores compartidos.

**Criterios de aceptación:** solo Platform y Shared están materializados; no existen carpetas vacías para los otros once módulos; el generador crea en un área de prueba una estructura válida y repetible; Shared no contiene reglas verticales; no hay endpoints/casos de uso comerciales.

**Pruebas:** autoload, boot de Platform, test automatizado de límites/dependencias y prueba del generador con limpieza segura del fixture generado.

**Riesgos:** carpetas ceremoniales vacías, Shared convertido en cajón de sastre o acoplamiento entre módulos.

**Estado:** `DONE` — 00D validado y aprobado explícitamente.

## S00-011 — API base, respuestas y OpenAPI

**Objetivo:** fijar el contrato técnico mínimo de `/api/v1`.

**Trabajo:** registrar grupo de rutas, respuesta singular/colección, errores, validación, correlation ID y documento OpenAPI inicial con health/base únicamente; configurar Sanctum como base técnica sin implementar flujos de identidad.

**Archivos esperados:** rutas versionadas, resources/error handler, configuración Sanctum, `docs/api/openapi.yaml` y contract tests.

**Dependencias:** S00-002, S00-003 y S00-010; aprobación DP-019.

**Criterios de aceptación:** `/api/v1` responde en JSON; errores incluyen código estable y correlation ID; OpenAPI valida y no publica funcionalidades fuera de alcance; Sanctum está instalado/configurado sin endpoints o permisos comerciales.

**Pruebas:** feature/contract tests para éxito, 404, 405, 422 y error interno sanitizado; lint OpenAPI.

**Riesgos:** contrato prematuro, filtrado de excepciones o divergencia implementación/esquema.

**Estado:** `DONE` — 00D validado y aprobado explícitamente.

## S00-012 — Correlation ID y logs estructurados

**Objetivo:** correlacionar requests, jobs y eventos con logs seguros.

**Trabajo:** middleware de correlation ID, contexto propagado a jobs, logging JSON y política de redacción.

**Archivos esperados:** middleware, configuración logging, soporte compartido mínimo y pruebas.

**Dependencias:** S00-004, S00-005 y S00-011.

**Criterios de aceptación:** ID entrante válido se conserva o se genera; aparece en respuesta/log/job; JSON consistente; sin PII, tokens ni secretos.

**Pruebas:** request→log, request→job, formato inválido, concurrencia y escaneo de campos prohibidos.

**Riesgos:** colisiones, confianza en IDs maliciosos, cardinalidad excesiva o fuga de datos.

**Estado:** `DONE` — 00E validado y aprobado explícitamente.

## S00-013 — Base técnica de idempotencia, outbox e inbox

**Objetivo:** proporcionar primitivas técnicas reutilizables de idempotencia y mensajería antes de mutaciones o consumidores comerciales.

**Trabajo:** definir almacenamiento de claves/hash/respuesta/estado; tabla outbox donde `event_id` y payload sean inmutables y los metadatos de publicación puedan actualizarse; tabla inbox con constraint único por `event_id`; dispatcher y consumidor exclusivamente técnicos.

**Archivos esperados:** migraciones técnicas de idempotencia/outbox/inbox, constraints e índices, contratos/servicios en Platform o Shared según ADR, dispatcher, consumidor técnico y pruebas.

**Dependencias:** S00-003, S00-005 y S00-010; aprobación DP-011.

**Criterios de aceptación:** misma clave+payload devuelve mismo resultado; misma clave+payload distinto falla; cambio+outbox es atómico; `event_id` y payload del outbox son inmutables; metadatos de publicación son actualizables; inbox deduplica por `event_id` mediante constraint único; un reintento no duplica el efecto observable; no existen consumidores comerciales.

**Pruebas:** unit, feature e integración transaccional; concurrencia; rollback; mutación rechazada de payload/`event_id`; actualización permitida de metadata; inserción inbox duplicada; reintento con un único efecto observable.

**Riesgos:** almacenar respuestas sensibles, crecimiento sin retención definida, carrera concurrente, mutación indebida del evento o confundir deduplicación técnica con una garantía de entrega que la cola no ofrece.

**Estado:** `DONE` — 00E validado y aprobado explícitamente.

## S00-014 — Pest, Pint y Larastan/PHPStan

**Objetivo:** establecer puertas de calidad reproducibles.

**Trabajo:** configurar Pest, Pint y Larastan/PHPStan; definir comandos y baseline solo si está justificado y vacío al inicio.

**Archivos esperados:** dependencias dev, `phpunit.xml`/Pest, `pint.json`, `phpstan.neon` y scripts Composer.

**Dependencias:** S00-002, S00-010 a S00-013; aprobación DP-016.

**Criterios de aceptación:** comandos deterministas y exitosos; análisis cubre módulos; baseline no oculta deuda nueva; fallas devuelven código no cero.

**Pruebas:** ejecutar suite, format check y análisis estático incluyendo una falla controlada durante configuración.

**Riesgos:** versiones incompatibles, reglas demasiado laxas o CI lento.

**Estado:** `DONE` — herramientas y fallas controladas verificadas; aprobación final de 00F pendiente.

## S00-015 — Pipeline GitHub Actions

**Objetivo:** validar automáticamente instalación, calidad y stack técnico.

**Trabajo:** workflow con Composer, caché, PostgreSQL/PostGIS, Redis, migraciones, Pest, Pint, Larastan/PHPStan y validación OpenAPI/Compose.

**Archivos esperados:** `.github/workflows/ci.yml` y documentación de checks.

**Dependencias:** S00-009, S00-011, S00-013 y S00-014; aprobación DP-013A.

**Criterios de aceptación:** workflow en PR/push acordados; permisos mínimos; versiones fijadas; todos los checks bloqueantes; sin secretos innecesarios.

**Pruebas:** ejecución verde desde checkout limpio y fallas controladas de test/formato/análisis.

**Riesgos:** diferencias con Docker local, caché obsoleta, permisos excesivos o consumo alto de minutos.

**Estado:** `DONE` — workflow, reproducción local y [GitHub Actions](https://github.com/nromero96/traepe/actions/runs/37159042917) verdes para `17784dbc12a00b24fb6de6d97d79268de2cf6409`.

## S00-016 — Instalación documentada y checkpoint final

**Objetivo:** demostrar que una persona nueva puede reproducir y verificar la fundación.

**Trabajo:** documentar prerrequisitos, instalación, comandos, troubleshooting, health, pruebas, reset seguro y arquitectura; ejecutar revisión final.

**Archivos esperados:** README raíz actualizado, `docs/installation/README.md`, runbook local y evidencia/checklist de salida.

**Dependencias:** S00-004 a S00-015.

**Criterios de aceptación:** instalación desde checkout limpio siguiendo solo documentación; stack sano; pruebas/CI verdes; alcance negativo verificado; aprobación del checkpoint.

**Pruebas:** dry run completo en entorno limpio, reinicio, restauración de estado local previsto y checklist de [aceptación](acceptance-criteria.md).

**Riesgos:** documentación dependiente de conocimiento tácito, comandos destructivos ambiguos o éxito solo en una máquina.

**Estado:** `IN_PROGRESS` — instalación/reinicio documentados y verificados; faltan revisión independiente y aprobación final.
