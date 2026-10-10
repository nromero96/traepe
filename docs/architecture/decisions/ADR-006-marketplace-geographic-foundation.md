# ADR-006 — Base geográfica vacía de Marketplace

- **Estado:** Aprobado por el usuario.
- **Fecha:** 10 de octubre de 2026.
- **Origen:** maestro v1.6 §§32–34, 54–57; ADR-002; DP-028.
- **Aprobación:** respuesta «Aprobar plan 02C (recomendado)» a la creación de countries, markets y service_zones vacías, propiedad de Marketplace, estados draft, zone_type fixture, PostGIS SRID 4326, sin seeds ni activación real.

## Contexto

El maestro define estas tablas y el tipo espacial persistente, pero no enumera los estados de mercado/zona ni los tipos de zona. La oleada de migración base tampoco aclara la propiedad modular. DP-028 registró esos vacíos y la ampliación del alcance local antes de implementar; el usuario aprobó una base vacía con restricciones provisionales explícitas.

## Decisión

Marketplace es dueño de countries, markets y service_zones, como tablas privadas del dominio geográfico. §54 conserva su significado de orden de migración; agrupar countries/markets en la oleada base no traslada su propiedad de negocio a Platform. Otros módulos accederán mediante contratos aprobados del dueño; Shared no contendrá estas reglas verticales.

02C crea únicamente una base vacía con bigint interno, ULID público válido/único, timestamps UTC, FK restrict, índices y constraints descritos en el [plan aprobado](../../sprints/sprint-02/checkpoint-02c-plan.md). Markets y service_zones admiten exclusivamente draft; zone_type exclusivamente fixture. No hay transiciones ni administración, importación o seeds. Datos sintéticos persistidos únicamente en bases de prueba temporales propias.

El polígono persistente usa geography(MultiPolygon,4326), conforme a §56, requerido, válido, no vacío y 2D. La comprobación de validez no emite NOTICE con coordenadas. No se inventan exclusión de solapamientos, unicidad de nombres, prioridad positiva, herencia de moneda ni configuración del piloto.

La consola y sonda HTTP de 02A/02B conservan los tres polígonos inline y local-coverage-v1. No consultan estas tablas ni interpretan draft como cobertura elegible. Las tablas quedan vacías en desarrollo y al instalar CI desde cero.

## Alternativas consideradas

Conservar únicamente fixtures inline aplazaba la integridad persistente sin resolver la propiedad. Crear datos o estados operativos exigía definir primero DP-001. Se eligió la base vacía de Marketplace para comprobar integridad y migraciones bajo el alcance local aprobado.

## Consecuencias y límites

La migración es aditiva y transaccional, específica de PostgreSQL/PostGIS; down rechaza un rollback automático que elimine estos datos. Revertirlos requiere un plan de datos revisado. Las pruebas desde base limpia y de segunda ejecución se realizan sin resetear desarrollo ni retirar volúmenes.

DP-001 sigue abierta. Estados/tipos operativos, activación, país/moneda/timezone del piloto, horarios y reglas vigentes requieren decisiones posteriores y constraints versionables. Una futura mutación administrativa necesitará autorización de servidor, concurrencia, historial/auditoría y outbox cuando corresponda. No se habilita la ruta /markets/resolve ni operaciones comerciales.

La aprobación de datos autorizó implementar y aplicar localmente la base descrita. Después de presentar el checkpoint terminado, el usuario respondió «Apruebo y autorizo» a su aprobación final y al commit/push/CI el 10 de octubre de 2026; véase [evidencia y autorización](../../sprints/sprint-02/checkpoint-02c-evidence.md#aprobación-y-publicación-autorizadas). No autoriza despliegue productivo ni cambios en otros módulos.
