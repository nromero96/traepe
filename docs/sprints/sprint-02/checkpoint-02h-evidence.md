# Evidencia de Checkpoint 02H — Diagnóstico comercial local

Fecha: 10 de octubre de 2026. **Estado: implementado y validado localmente; cierre y commit/push/CI autorizados explícitamente.**

## Autorización y trazabilidad

El usuario respondió «Apruebo implementarlo» al [plan 02H](checkpoint-02h-plan.md), resolviendo [DP-033](../../architecture/decisions/pending-decisions.md#dp-033--diagnóstico-local-de-contexto-comercial). Maestro v1.6 §§3.1, 9, 32–33, 36, 52–56, 60, 72 y 78–79; continuidad de [ADR-007](../../architecture/decisions/ADR-007-marketplace-commercial-foundation.md). La aprobación inicial permitió implementar y validar; la autorización final de commit/push/CI se registra al cierre. Ninguna aprobación habilita operación productiva.

Base main / origin/main `6d3dbb53bbcb9056120589c260208c0ab67fa79c`, 02G con [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38093771857), incluidos run/job y cuatro pasos obligatorios sobre ese SHA. El registro de publicación 02G se incorporó al preparar 02H.

## Resultado

`marketplace:local-commercial-context {merchant_public_id} {market_public_id} {branch_public_id}` valida tres ULID estrictos antes de DI. CommercialContext y sus referencias son inmutables; Domain/Application no dependen del framework. El adaptador ejecuta una sola lectura parametrizada privada de branches/merchants/markets, exige las relaciones exactas y draft en las tres entidades, y selecciona solo la constante 1. El caso de uso retorna coincidencia booleana.

Salida singular exacta `{"fixture_version":"local-commercial-context-v1","status":"matched"}` o not_found. Ambos exit 0; ausentes y relaciones cruzadas indistinguibles. Entrada inválida, entorno denegado o fuente/resolución indisponible dan exit 1 y mensaje fijo sin eco, SQL, bindings, stacktrace o detalles comerciales. Provider registra comando/puerto solo local/testing y comando/adaptador vuelven a denegar fuera de esos entornos antes de DI/SQL.

No HTTP, esquema, escritura, dependencias, modelos, permisos/membresías o contratos entre módulos nuevos. No consulta Identity/Platform/zonas ni cambia las sondas geográficas o perfiles fijos 02F. Matched es una relación de borradores y no representa permiso, activación, cobertura o servicio disponible.

## Validaciones

| Validación | Resultado |
|---|---|
| Pint | 203 archivos correctos |
| PHPStan nivel 8 | Sin errores |
| OpenAPI 3.0.3 / API 1.5.0 y snapshot v1 existentes | Correctos; negativos del lint rechazados, contratos sin cambios |
| Unit / Feature completas | 160 pruebas, 3166 aserciones |
| Integration completa PostgreSQL/PostGIS | 68 pruebas, 1654 aserciones |
| Total | **228 pruebas, 4820 aserciones** |
| Casos nuevos directos | 4 Unit, 6 Feature y 3 Integration; 13 pruebas, 213 aserciones |
| Fundación HTTP/DB y comando | Correcta; not_found exacto, todos los conteos preservados y seis tablas Marketplace vacías |
| Runtime FPM | Archivos propios y liveness correctos |
| Puertas negativas Pest/Pint/PHPStan | Fallos controlados rechazados con exit 1 |
| Alcance, formato documental y secretos | 23 archivos del manifiesto; diff/UTF-8 correctos y sin secretos |
| Enlaces locales | 171 referencias verificadas |
| Maestro y staging | Hash original conservado; staging vacío |
| Servicios locales / bases temporales | Ocho saludables; ninguna base temporal remanente |

Comandos ejecutados en Docker con .env.docker ignorado: Pint sobre los archivos propios, Pest inicial de las dos clases nuevas Unit/Feature, `composer quality`, `composer test:integration`, `php tests/Support/verify-foundation.php`, `verify-runtime-permissions.php` bajo www-data y `verify-quality-gates.php`. No dependencias instaladas, aplicaciones generadas, migraciones ejecutadas ni infraestructura modificada para 02H.

Cobertura y preservación:

- ULID estrictos en las tres referencias, límites válidos, longitud/mayúsculas/espacios/control/caracteres excluidos y errores fijos; referencia/contexto inmutables y caso de uso puro que pasa el contexto exacto al puerto.
- Salida cerrada para matched/not_found sin eco; cada argumento inválido falla antes de resolver el puerto. Errores de resolución/fuente con contenido privado dan únicamente el mensaje fijo.
- Comando previamente registrado deniega production/staging antes de validar argumentos o resolver bindings; adaptador deniega antes de SQL. Procesos nuevos en ambos entornos no registran comando ni puerto.
- PostgreSQL/PostGIS real: base vacía, relación exacta, referencias desconocidas y relaciones cruzadas entre comercios/mercados; varias sucursales por par y un comercio en distintos mercados. Desconocidos y cruces producen la misma salida.
- Listener de prueba cuenta exactamente una consulta por diagnóstico y conserva únicamente contadores/booleanos de inspección; comprueba SELECT 1, parámetros exactos y filtros draft de las tres entidades, sin consultas a Identity/Platform/zonas ni mutaciones. No relaja constraints para fabricar estados operativos.
- Hashes de filas de todas las tablas públicas de cada base propia antes/después idénticos; sin nuevos datos ni cambios de esquema. Las bases positivas aisladas se eliminan al terminar; nunca reset/rollback local ni eliminación de volúmenes.
- Regresiones completas conservan geografía, sesión/OTP/CSRF reales, DP-026, idempotencia/concurrencia/rollback de 02F y límites modulares. No interfaces HTTP ni contracts/snapshot nuevos.

Fundación por Nginx conserva salud/correlación, Horizon anónimo 401, lectura persistida 401 y creación anónima sin CSRF 419, sin cambios de datos/claims. El nuevo comando devuelve not_found sobre desarrollo vacío y compara todos los conteos previos, incluidos Identity/Platform y migraciones. Countries, markets, service_zones, marketplace_local_fixture_operations, merchants y branches mantienen 0 registros; no usuarios, roles ni grants nuevos. Logs técnicos sin credenciales, ocho servicios saludables y ninguna base temporal remanente.

## Archivos del manifiesto (23)

- [docs/README.md](../../../docs/README.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/sprints/sprint-02/README.md](../../../docs/sprints/sprint-02/README.md)
- [docs/sprints/sprint-02/checkpoint-02g-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02g-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02h-plan.md](../../../docs/sprints/sprint-02/checkpoint-02h-plan.md)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/MerchantPublicId.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/MerchantPublicId.php)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/BranchPublicId.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/BranchPublicId.php)
- [apps/api/app/Modules/Marketplace/Domain/Commerce/CommercialContext.php](../../../apps/api/app/Modules/Marketplace/Domain/Commerce/CommercialContext.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/LocalCommercialContextSource.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/LocalCommercialContextSource.php)
- [apps/api/app/Modules/Marketplace/Application/Commerce/ProbeLocalCommercialContext.php](../../../apps/api/app/Modules/Marketplace/Application/Commerce/ProbeLocalCommercialContext.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresLocalCommercialContextSource.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/Commerce/PostgresLocalCommercialContextSource.php)
- [apps/api/app/Modules/Marketplace/Interfaces/Console/ProbeLocalCommercialContext.php](../../../apps/api/app/Modules/Marketplace/Interfaces/Console/ProbeLocalCommercialContext.php)
- [apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php](../../../apps/api/app/Modules/Marketplace/Infrastructure/MarketplaceServiceProvider.php)
- [apps/api/tests/Unit/CommercialContextTest.php](../../../apps/api/tests/Unit/CommercialContextTest.php)
- [apps/api/tests/Feature/LocalCommercialContextCommandTest.php](../../../apps/api/tests/Feature/LocalCommercialContextCommandTest.php)
- [apps/api/tests/Integration/LocalCommercialContextPostgresTest.php](../../../apps/api/tests/Integration/LocalCommercialContextPostgresTest.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/marketplace-and-purchase.md](../../../docs/specifications/marketplace-and-purchase.md)
- [docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md](../../../docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md)
- [docs/sprints/sprint-02/checkpoint-02h-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02h-evidence.md)

## Límites pendientes

DP-001 y demás decisiones productivas permanecen abiertas. No onboarding, administración/MFA, activación, reglas fiscales/riesgo, contratos/comisiones, horarios/cobertura por sucursal, membresías, catálogo, stock o elegibilidad para compra. Desarrollo debe conservar seis tablas Marketplace vacías y datos Identity/Platform previos; fixtures positivos solo en bases temporales propias eliminadas al finalizar.

## Aprobación y publicación autorizadas

Después de presentar el resultado terminado y las validaciones completas, el usuario respondió «Autorizo» el 10 de octubre de 2026 a la solicitud explícita de commit, push y CI de 02H. Esta autorización satisface el cierre reservado en el plan y la restricción de [AGENTS.md](../../../AGENTS.md). Se publica exclusivamente el manifiesto de 23 archivos; no autoriza despliegue ni operación real.

Este registro precede al commit. El SHA publicado y el resultado de GitHub Actions se comprobarán sobre ese commit exacto y se comunicarán al finalizar, incluidos build/migración, calidad/integración, reinicio/persistencia y limpieza.
