# Propuesta de Checkpoint 02C — Base geográfica vacía

Fecha: 10 de octubre de 2026. **Estado: plan aprobado mediante DP-028; implementación y aplicación local completadas y validadas; checkpoint, commit, push y CI aprobados por el usuario.** Véase [evidencia de 02C](checkpoint-02c-evidence.md).

02B está aprobado/publicado en `b4b81f738790a796cfc0b27e4752c80b849f13a2`; [CI correcta](https://github.com/nromero96/traepe/actions/runs/38075664329), incluidos reinicio/persistencia y limpieza. El usuario solicitó continuar. La cobertura sintética y los criterios de DP-027 siguen vigentes; DP-001 no define todavía el piloto operativo.

## Fuente y decisión necesaria

Maestro v1.6 §§32–34, 54–57 y 64; ADR-002; AGENTS.md. El maestro define countries, markets y service_zones y exige estados controlados, FK e índices. No enumera MarketStatus, ZoneStatus o zone_type en §57. §54 agrupa countries/markets en la oleada de base Platform, mientras Marketplace contiene el dominio de cobertura; el orden de migración no confirma por sí mismo el dueño de los datos. Además, la excepción local aprobada hasta 02B excluye persistir mercados/zonas.

DP-028 requería aprobar una base vacía, su propiedad modular y restricciones provisionales. Se preparó este plan sin implementar la migración afectada; el usuario respondió «Aprobar plan 02C (recomendado)» a la propuesta. Los valores descritos abajo son la concreción aprobada para preparación local, no estados operativos productivos del maestro. Véase [ADR-006](../../architecture/decisions/ADR-006-marketplace-geographic-foundation.md).

## Alcance propuesto

- Marketplace será dueño de countries, markets y service_zones, como tablas privadas. Otros módulos utilizarán contratos del dueño cuando sean necesarios; Shared/Platform no contendrán estas reglas verticales.
- Crear exclusivamente estas tres tablas mediante una migración aditiva y transaccional PostgreSQL. Sin seeds, importación, usuarios, grants, API de administración ni activación.
- Markets y service_zones admitirán únicamente `status=draft`, sin transiciones ni selección operativa. Service_zones admitirá únicamente `zone_type=fixture`, marcador técnico de preparación local.
- Las tres tablas quedarán vacías en desarrollo y en la instalación limpia de CI. Las pruebas insertarán datos sintéticos solo en bases temporales propias.
- La consola y la sonda HTTP 02A/02B conservarán sus tres polígonos inline y contrato local-coverage-v1; no leerán estas tablas ni volverán elegible un borrador.
- DP-001 seguirá bloqueando ciudad/país, distritos, moneda y timezone del piloto, horarios, reglas vigentes y activación real. No se implementarán comercios, sucursales, zone_rules, direcciones, geocodificación, tarifas, ETA ni frontend.

## Esquema revisable propuesto

En las tres tablas: `id` bigint interno, `public_id` char(26) ULID válido/único e indexado; `created_at` y `updated_at` timestamptz requeridos con hora actual inicial. Sin soft delete ni metadata de contenido abierto.

| Tabla | Campos adicionales | Integridad e índices propuestos |
|---|---|---|
| countries | code char(2), name varchar(255), currency_code char(3) | code único, dos letras mayúsculas; currency_code tres letras mayúsculas; nombre no vacío |
| markets | country_id bigint, name varchar(255), timezone varchar(64), currency_code char(3), status varchar(40) default draft, version integer default 1 | FK restrict a countries; nombre/timezone no vacíos; moneda de tres letras mayúsculas; CHECK status=draft y version>=1; índice country_id |
| service_zones | market_id bigint, name varchar(255), zone_type varchar(40) default fixture, polygon geography(MultiPolygon,4326), priority integer, status varchar(40) default draft, version integer default 1 | FK restrict a markets; nombre no vacío; CHECK zone_type=fixture, status=draft, version>=1; polígono requerido, válido, no vacío y 2D; índice market_id y GIST(polygon) |

Estas validaciones de códigos son de formato; no seleccionan ni certifican un país, moneda o registro productivo. Currency_code y timezone de mercado deben proporcionarse explícitamente: no se heredan ni reciben un default del piloto. La validez de una configuración operativa se resolverá antes de cualquier futura activación, no se infiere de que un draft pueda almacenarse.

Priority será un entero PostgreSQL con signo, sin imponer una regla nueva de positividad. No se agrega unicidad de nombres ni prohibición de solapamiento: el maestro permite zonas superpuestas. Tampoco se exige que una moneda de mercado coincida con la del país por una regla inventada.

El tipo geography(MultiPolygon,4326) sigue §56 para datos persistentes; no sustituye la geometría plana de los fixtures inline ya aprobados en 02A. Validación topológica explícita sobre el cast a geometry, con ST_IsValid(..., 0) para evitar NOTICE con detalles espaciales; comprobación separada de no vacío y 2D. No se importan coordenadas externas ni se corrigen geometrías automáticamente. Un futuro ingreso de geodatos deberá validar también coordenadas originales antes de conversiones o normalizaciones.

## Implementación tras aprobar DP-028

1. Crear la migración con constraints nombrados, FK restrict e índices; política forward-only coherente con las últimas migraciones. No generar módulos, carpetas o adaptadores sin uso.
2. Probar en PostgreSQL/PostGIS real: tablas inicialmente vacías, bigint/ULID, unicidad, FK, borrados restrict, estados/tipo de zona no admitidos, campos requeridos, versiones e integridad espacial. Fixtures temporales válidos y rechazos esperados, sin depender del orden.
3. Verificar migración desde base limpia y segunda ejecución idempotente, usando bases propias. No resetear desarrollo ni eliminar volúmenes.
4. Adaptar los checks que hoy exigen ausencia de markets/service_zones para comprobar existencia vacía; merchants/branches y demás módulos comerciales seguirán ausentes. Demostrar que las sondas 02A/02B conservan resultados e ignoran borradores persistidos.
5. Aplicar la migración aditiva en Docker local solo después de pasar las pruebas aisladas; comprobar que las tres tablas continúan vacías y se conserva la información existente.
6. Ejecutar Pint, PHPStan, OpenAPI, Unit/Feature, Integration, fundación, salud Docker, revisión del diff y escaneo de secretos. Actualizar especificación, modelo de datos y evidencia.
7. Presentar el checkpoint terminado para aprobación de commit/push/CI. La decisión de datos no autoriza por sí misma su publicación.

## Puertas posteriores

Los futuros estados/tipos operativos requieren una nueva decisión y constraints versionables, con validación de servidor, autorización, concurrencia, auditoría/historial y eventos cuando corresponda. No se añade una transición draft→active en 02C ni se define cobertura aplicable a checkout.

La propiedad quedó aprobada antes de crear la migración. Un cambio posterior de dueño o la activación del piloto requieren especificación y aprobación nuevas antes de implementar.

## Referencias técnicas verificadas

[Tipos y administración de PostGIS 3.5](https://postgis.net/docs/manual-3.5/using_postgis_dbmanagement.html) distingue geography/geometry e índices espaciales. [ST_IsValid 3.5](https://postgis.net/docs/manual-3.5/ST_IsValid.html) comprueba validez 2D y documenta la variante sin NOTICE. Respaldan la técnica propuesta; no aprueban propiedad de datos ni reglas comerciales.
