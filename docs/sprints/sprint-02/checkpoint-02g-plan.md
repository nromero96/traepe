# Plan aprobado de Checkpoint 02G — Base vacía de comercios y sucursales

Fecha: 10 de octubre de 2026. **Estado: plan aprobado mediante «Apruebo»; implementado y validado localmente, con cierre y commit/push/CI autorizados después mediante «Si autorizo».**

02F está publicado en `3c9448c37c3736a155cd794b3f9bd2861e557796`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38091316706), incluidos build/migración, calidad/integración, reinicio/persistencia y limpieza. El usuario solicitó continuar. Al preparar el plan, desarrollo conservaba vacías countries, markets, service_zones y marketplace_local_fixture_operations, sin tablas merchants/branches. DP-032 fue aprobada después; la implementación y aplicación local terminadas se registran en la evidencia de 02G.

## Propósito y decisión requerida

Maestro v1.6 §§3.1, 9, 32–34, 36, 52–56, 60, 72 y 78–79. La siguiente base de Marketplace es la entidad comercio con varias sucursales. Prepara relaciones necesarias para el futuro catálogo y permisos por comercio/sucursal, sin habilitar onboarding ni operación.

§36 enumera merchants y branches y sus campos esenciales, pero no concreta estados iniciales, niveles de riesgo, tratamiento/formato/unicidad de tax_id, o el ciclo de activación. §57 no incluye enums MerchantStatus/BranchStatus. Las aprobaciones DP-028 a DP-031 cubren geografía/fixtures y no esas nuevas entidades. [DP-032](../../architecture/decisions/pending-decisions.md#dp-032--base-local-vacía-de-comercios-y-sucursales) registra el vacío; [AGENTS.md](../../../AGENTS.md) exige detener la implementación afectada si la decisión modifica datos o negocio.

El usuario aprobó una base técnica vacía con constraints restrictivos: comercios y sucursales exclusivamente draft, tax_id y risk_level solo NULL, sucursales ligadas explícitamente a su comercio y mercado. No define estados/taxonomías operativos ni decisiones fiscales. El alcance ficticio local anterior se conserva; datos positivos nuevos solo en bases temporales propias de prueba.

Las pantallas del maestro sí describen sucursal abierto/pausado (T-03) y acciones aprobar/observar/suspender en comercios (A-06), además de RUC/riesgo en administración. Esta propuesta no sustituye esas reglas por draft ni interpreta esos verbos como un enum de persistencia aprobado; limita exclusivamente la base de prueba vacía. Habilitar esas pantallas y estados requiere su especificación operativa posterior.

## Propiedad y relaciones

Marketplace es dueño privado de ambas tablas. No se agregan modelos, repositorios, puertos, controladores o carpetas sin consumidor actual; este checkpoint se limita a migración, pruebas y especificación/ADR utilizados.

Merchant representa la raíz comercial y no se limita a un país, mercado, tienda o categoría. Una Branch pertenece a exactamente un Merchant y un Market. Un Merchant puede tener Branches en diferentes Markets; no se impone un market_id al comercio ni igualdad de país/moneda heredada. Identificación por bigint interno y ULID público válido/único, sin exponer bigint.

Las dos FK de Branch apuntan exclusivamente a tablas privadas del mismo módulo, con ON DELETE RESTRICT. No FK/consultas a users ni ampliación de tablas/bindings de Identity. Los scopes platform/merchant/branch aprobados en DP-026 permanecen intactos; conocer un ULID de estas tablas no concede permisos. No hay membresías ni asignaciones nuevas.

## Esquema propuesto

Nueva migración aditiva `2026_10_10_000005_create_marketplace_commercial_foundation.php`. Columnas sin nullable salvo tax_id/risk_level:

| Tabla | Campo | Tipo / constraint |
|---|---|---|
| merchants | id | bigint interno generado |
| merchants | public_id | char(26), ULID válido, unique/index |
| merchants | legal_name / trade_name | varchar(255), requeridos, length(btrim(...)) > 0; sin nombres por defecto |
| merchants | tax_id | varchar(64), nullable; CHECK tax_id IS NULL en este ejercicio |
| merchants | status | varchar(40), default draft; CHECK solo draft |
| merchants | risk_level | varchar(40), nullable; CHECK risk_level IS NULL en este ejercicio |
| merchants | version | integer, default 1; CHECK >=1 |
| merchants | created_at / updated_at | timestamptz UTC, requeridos, default CURRENT_TIMESTAMP |
| branches | id / public_id | bigint generado / char(26) ULID válido, unique/index |
| branches | merchant_id | bigint requerido, FK merchants restrict |
| branches | market_id | bigint requerido, FK markets restrict |
| branches | name | varchar(255), requerido, length(btrim(name)) > 0; sin default |
| branches | point | geography(Point,4326), requerido, válido/no vacío/2D, coordenadas finitas y en rangos WGS84; índice GIST |
| branches | timezone | varchar(64), requerido, length(btrim(timezone)) > 0; sin default/herencia |
| branches | status / version | varchar(40) solo draft default / integer >=1 default 1 |
| branches | created_at / updated_at | timestamptz UTC, requeridos, default CURRENT_TIMESTAMP |

ULID estricto mayúsculo: `^[0-7][0-9A-HJKMNP-TV-Z]{25}$`. No unique de nombres, tax_id ni combinación merchant/market: varias sucursales del mismo comercio pueden pertenecer al mismo mercado. Los campos tax_id/risk_level del maestro se conservan explícitamente vacíos; sus tipos/límites son técnicos provisionales y cualquier habilitación de valores requerirá especificación y migración posteriores aprobadas.

Índices: unique public_id en ambas tablas; branches (merchant_id, market_id) para el alcance combinado y la FK de comercio, branches (market_id) para alcance de mercado/FK y GIST(point). No índices de campos sin consulta prevista ni relaciones polimórficas.

Point se valida con ST_IsValid(point::geometry, 0), NOT ST_IsEmpty, ST_NDims=2 y rangos ST_X/ST_Y; se conserva geography nativa. Los fixtures de prueba usan exclusivamente puntos sintéticos cerca del origen matemático, timezone Etc/UTC y nombres Synthetic Merchant/Branch. No se geocodifica ni importa ubicación real. El tipo geography puede normalizar coordenadas al convertir una entrada fuera de rango: un futuro límite de escritura deberá validar los valores originales con GeographicPoint antes del cast, como en las sondas actuales. No se ofrece entrada ni escritura por HTTP/consola en 02G.

Sin deleted_at: no se aprueba recuperación/borrado de perfiles ni su política. Sin tablas de historial: no hay caso de uso de mutación ni transición implementado. Una futura creación/edición administrativa requerirá autorización de servidor, historial/auditoría, correlación, idempotencia/concurrencia y outbox si publica eventos, además de MFA sensible según §60; este esquema vacío no la autoriza.

## Migración y operación local

Una migración transaccional crea ambas tablas y sus índices/checks/FK, sin modificar tablas anteriores, constraints draft/fixture, los perfiles de 02F ni sus historiales. Down es forward-only: rechaza eliminación automática y exige un plan revisado. No hace seeds, backfill ni cambios sobre registros existentes.

Se prueba primero en bases PostgreSQL/PostGIS temporales propias desde cero. Solo después de validar se aplica localmente la nueva base vacía. Se comparan conteos previos de todas las tablas: ningún dato cambia, únicamente se agregan dos tablas vacías y un registro de migración. La segunda ejecución debe indicar Nothing to migrate. No reset, migrate:fresh local, rollback local, eliminación de volúmenes ni limpieza ajena.

Desarrollo conserva seis tablas Marketplace vacías: countries, markets, service_zones, marketplace_local_fixture_operations, merchants y branches. Sin usuarios, roles, grants ni datos de prueba nuevos en desarrollo. No dependencias/aplicaciones nuevas, ni infraestructura/CD/hosting.

## Pruebas y regresiones previstas

1. Desde una base aislada vacía: tipos, longitudes, nulabilidad, defaults, UTC, ULIDs/checks, FK restrict, índices y seis tablas Marketplace vacías; segunda migrate sin cambios.
2. Casos sintéticos válidos: un comercio con varias sucursales, incluyendo distintos mercados; varios comercios en el mismo mercado. Verificar referencias y separación de merchant_id/market_id, sin imponer unicidades de nombre no especificadas.
3. Rechazos: ULID inválido/duplicado, nombres/timezone vacíos, campos requeridos ausentes, tax_id/risk_level no NULL, estados operativos, version<1, comercio/mercado desconocidos y eliminación de comercio/mercado referenciado.
4. PostGIS real: point nativo SRID 4326, 2D/no vacío/válido; rechazar geometría de tipo equivocado, dimensión/SRID incorrectos y coordenadas no finitas. Comprobar orden longitud/latitud y GIST. No interpretar la existencia de point como cobertura disponible.
5. Down rechazado conserva tablas/datos sintéticos; migración aditiva no modifica las bases anteriores. Los datos positivos se destruyen únicamente al eliminar la base propia de prueba.
6. Las sondas 02A–02E siguen limitadas a sus fuentes geográficas. Actualizar las antiguas aserciones de ausencia de merchants/branches en LocalCoveragePostgisTest, LocalCoverageHttpPostgisTest y GeographicFoundationTest por comprobaciones equivalentes de tablas aprobadas vacías/independencia de datos; conservar ausencia de zone_rules, addresses, geocoding_results y otras tablas operativas. Incluir sentinelas sintéticos en pruebas para demostrar que no se consultan estas tablas ni alteran resultados.
7. 02F conserva creación fija/idempotencia/snapshot y no crea comercios/sucursales. Sesión/CSRF/permisos/rate limit, runtime fuera de local y límites modulares mantienen su comportamiento.
8. Fundación HTTP por Nginx comprueba seis tablas Marketplace vacías, datos Identity/Platform preservados, sin bases temporales remanentes y ocho servicios saludables. No implementa /stores ni amplía /markets/resolve.
9. Pint, PHPStan nivel 8, OpenAPI/snapshot, Unit/Feature e Integration completas; validaciones de permisos de runtime y puertas de calidad disponibles, privacidad/secretos, enlaces/diff y hash del maestro.
10. Especificación, modelo de datos, arquitectura y ADR de propiedad/restricciones actualizados, con evidencia y manifiesto exacto de archivos modificados.

## Archivos previstos y criterios de cierre

- Nueva migración, Integration CommercialFoundationTest y ADR-007 de base comercial local vacía.
- Ajustes proporcionales en las tres pruebas geográficas y tests/Support/verify-foundation.php, manteniendo o reforzando sus garantías.
- Documentación de Marketplace, arquitectura/datos/seguridad, decisiones, Sprint 02 y evidencia 02G; registro de publicación 02F.
- Ningún código de aplicación/API/modelo nuevo sin uso, dependencia, seed, permiso, dato operativo o contrato de otro módulo.

El cambio está terminado cuando cumple esos criterios, supera las puertas de calidad, conserva desarrollo vacío y presenta evidencia concreta para revisión. La aprobación explícita de DP-032 autorizó implementar y validar esta base, y aplicar la migración local vacía. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Si autorizo»; véase el [registro de cierre](checkpoint-02g-evidence.md#aprobación-y-publicación-autorizadas).

## Límites pendientes

DP-001 continúa abierta para el piloto y no cambia su responsable/fecha. DP-002/DP-005 conservan cobros, comisiones y facturación; DP-010 mantiene proveedores productivos, incluida geocodificación. Las reglas de riesgo/identificación fiscal, estados operativos, membresías/MFA administrativa, contratos, horarios, cobertura por sucursal, preparación, disponibilidad, activación y política de eliminación no quedan aprobadas por esta base vacía.

No hay merchant_documents, merchant_contracts, commission_rules, branch_schedules, branch_schedule_exceptions, branch_service_areas, branch_settings ni branch_memberships. Tampoco catálogo, inventario, discovery, checkout o finanzas. Es una preparación de datos para el siguiente bloque del maestro, con restricciones locales explícitas.
