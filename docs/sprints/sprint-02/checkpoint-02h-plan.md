# Plan aprobado de Checkpoint 02H — Diagnóstico de contexto comercial local

Fecha: 10 de octubre de 2026. **Estado: plan aprobado mediante «Apruebo implementarlo»; implementado y validado localmente, con cierre y commit/push/CI autorizados después mediante «Autorizo».**

Base: main / origin/main `6d3dbb53bbcb9056120589c260208c0ab67fa79c`, 02G publicado y con [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38093771857). Run, job foundation y build/migración, calidad/integración, reinicio/persistencia y limpieza se verificaron sobre ese SHA. Validación local de 02G: 215 pruebas y 4592 aserciones; fundación nuevamente correcta, seis tablas Marketplace vacías y sin bases temporales remanentes. El usuario solicitó continuar.

## Propósito y autorización

Maestro v1.6 §§3.1, 9, 32–33, 36, 52–56, 60, 72 y 78–79. Merchant es la raíz comercial y Branch pertenece explícitamente a Merchant y Market. [ADR-007](../../architecture/decisions/ADR-007-marketplace-commercial-foundation.md) y DP-032 aprobaron únicamente la base vacía de 02G; no aprobaron una interfaz para consultar registros comerciales privados. Las sondas geográficas existentes deben seguir independientes de esas tablas.

[DP-033](../../architecture/decisions/pending-decisions.md#dp-033--diagnóstico-local-de-contexto-comercial) registra la ampliación aprobada por el usuario mediante «Apruebo implementarlo». Se resolvió el bloqueo de alcance de acceso/salida de [AGENTS.md](../../../AGENTS.md) antes de implementar. Este diagnóstico no elige estados operativos ni adelanta onboarding, membresías o permisos de tiendas.

Preparar un diagnóstico de consola de solo lectura permite verificar el alcance conjunto comercio/mercado/sucursal antes de ofrecer contratos a otros módulos. El resultado describe una relación entre borradores y nunca una sucursal abierta, cobertura disponible, autorización de usuario o elegibilidad para compra.

## Interfaz y resultado concretos

Nuevo comando exclusivamente local/testing:

```text
marketplace:local-commercial-context {merchant_public_id} {market_public_id} {branch_public_id}
```

Los tres argumentos son requeridos, ULID estricto mayúsculo `^[0-7][0-9A-HJKMNP-TV-Z]{25}$`, sin normalización silenciosa. No se admite bigint ni búsqueda/listado por nombre, punto o identificador fiscal. Formato inválido falla antes de resolver el puerto o consultar PostgreSQL.

Salida singular, sin eco de entrada:

```json
{"fixture_version":"local-commercial-context-v1","status":"matched"}
```

`matched` exige Branch draft con el ULID indicado, ligada exactamente al Merchant draft y Market draft indicados. La comparación usa los tres public_id en una sola sentencia parametrizada; los joins usan FK internas únicamente dentro de Marketplace. `not_found` cubre por igual referencias desconocidas, relaciones cruzadas y registros que no cumplan draft. No distingue qué referencia existe.

Ambos resultados técnicos devuelven exit 0. Entrada inválida, entorno denegado o fallo de infraestructura devuelven exit 1 con mensajes fijos/genéricos distintos, sin SQL, bindings, stacktrace ni valores de los argumentos. No se devuelve legal_name, trade_name, tax_id, risk_level, name, timezone, point, bigint, timestamps ni versiones de filas.

## Composición y aislamiento

- Domain: MerchantPublicId, BranchPublicId y referencia inmutable CommercialContext; reutilizar el MarketPublicId puro existente. Todos los componentes nuevos tienen consumidor en este caso de uso.
- Application: ProbeLocalCommercialContext y puerto LocalCommercialContextSource; recibe la referencia y devuelve únicamente coincidencia booleana. No depende de Laravel ni de repositorios de otros módulos.
- Infrastructure: PostgresLocalCommercialContextSource implementa una única lectura parametrizada de branches/merchants/markets. No selecciona nombres, coordenadas ni datos fiscales, no llama a Identity/Platform ni consulta sus tablas privadas.
- Interfaces: ProbeLocalCommercialContext valida formato, aplica el guard de entorno antes de DI/SQL, invoca el caso de uso y transforma el booleano al resultado técnico.
- MarketplaceServiceProvider registra comando y binding solo en local/testing. El adaptador vuelve a denegar fuera de esos entornos antes de SQL; también se verifica un comando previamente registrado cuando cambia el entorno y procesos nuevos production/staging.

El operador de consola usa el acceso técnico al entorno local existente, como en 02D. No hay endpoint HTTP, sesión, capacidad, grant, membresía o resolución global de recursos nuevos. APP_ENV no concede acceso HTTP ni privilegios de tienda. No se publica un contrato comercial para Catalog ni se consulta Marketplace desde otros módulos.

Una sentencia mantiene las tres relaciones en un mismo snapshot PostgreSQL. No cachés ni proyecciones; PostgreSQL conserva la fuente de verdad. No se evalúan polígonos, ubicación, horarios, stock, contratos, comisiones o estado operativo. Las sondas 02A–02E y creación fija 02F conservan sus fuentes/contratos.

## Datos y operación

Sin migraciones, seeds, backfill, escrituras, historial nuevo, eventos o efectos externos. No se instala dependencia ni genera aplicación. Desarrollo conserva countries, markets, service_zones, marketplace_local_fixture_operations, merchants y branches vacías, y los datos previos de Identity/Platform. No se crean usuarios, roles ni grants.

Fixtures comerciales positivos únicamente en bases PostgreSQL/PostGIS temporales propias mediante PostgresTestCase. Usar nombres Synthetic Merchant/Branch, timezone Etc/UTC y puntos sintéticos cerca del origen matemático. No ubicaciones o identidades reales. Las pruebas eliminan solo sus bases aleatorias propias; no reset, rollback local ni eliminación de volúmenes.

Fundación comprobará el nuevo comando contra la base vacía: not_found y conteos anteriores preservados. La comprobación anterior de HTTP/CSRF/privacidad y las seis tablas vacías se conserva. No se modifica OpenAPI 1.5.0 ni snapshot v1 porque no hay interfaz HTTP nueva.

## Pruebas y criterios de cierre

1. Unit: tres referencias estrictas, límites de ULID, strings inválidos/lowercase/espacios/control, contexto inmutable y caso de uso de coincidencia/ausencia sin framework.
2. Feature: salida exacta sin eco/datos sensibles, ausencia como resultado correcto; cada argumento inválido falla antes de resolver/acceder al puerto. Error de fuente con contenido privado se transforma en mensaje fijo.
3. Entorno: production/staging nuevos no registran comando ni puerto; comando ya registrado deniega antes de DI aunque los bindings no existan; adaptador deniega antes de SQL. No nuevo acceso HTTP.
4. Integration real: coincidencia exacta, referencias desconocidas, branch de otro merchant o market, varios merchants/branches en un mercado, un merchant en distintos mercados y varios branches del mismo par. No depende del orden de ejecución.
5. Snapshot de filas antes/después y listener limitado a contadores para demostrar lectura única, sin INSERT/UPDATE/DELETE ni consultas a Identity, Platform, zonas u otras tablas. Nunca imprimir SQL/bindings ni nombres/ubicaciones. Validar la sentencia draft sin relajar los CHECK de 02G para fabricar estados operativos.
6. Regresión completa de geografía, sesión/CSRF, permisos DP-026, fixtures 02F/idempotencia, límites modulares y constraints de 02G. Pint, PHPStan nivel 8, OpenAPI/snapshot, Unit/Feature e Integration disponibles.
7. Fundación HTTP/DB, permisos de runtime y puertas negativas disponibles; seis tablas vacías, datos anteriores preservados, sin bases temporales remanentes y ocho servicios saludables.
8. Especificación/arquitectura/seguridad documentan el diagnóstico aprobado y su privacidad; evidencia de resultados/manifiesto, diff/UTF-8/enlaces/secretos y hash del maestro correctos.

Archivos previstos: los siete componentes nuevos de Domain/Application/Infrastructure/Interfaces indicados arriba, MarketplaceServiceProvider, tres clases de pruebas y verify-foundation.php; documentación relacionada y evidencia 02H. Sin nuevas carpetas vacías ni modelos/contratos sin consumidor.

La aprobación de DP-033 autorizó implementación y validación de este diagnóstico únicamente, reservando la publicación al cierre. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Autorizo»; véase el [registro de cierre](checkpoint-02h-evidence.md#aprobación-y-publicación-autorizadas).

## Límites pendientes

DP-001 y las demás decisiones productivas permanecen abiertas, con responsables/fechas intactos. La existencia de la relación no concede permisos ni implica activación, fiscalidad/riesgo favorables, cobertura, catálogo, stock, horarios, contrato o servicio disponible. Estados operativos, onboarding, membresías, administración/MFA y futuros contratos entre módulos requerirán especificación y autorización posteriores.
