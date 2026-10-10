# ADR-007 — Base comercial local vacía de Marketplace

- **Estado:** Aprobado para el ejercicio local, mediante DP-032.
- **Fecha:** 10 de octubre de 2026.
- **Fuente:** maestro v1.6 §§3.1, 9, 32–34, 36, 52–56, 60, 72 y 78–79.
- **Aprobación:** el usuario respondió «Apruebo» al [plan 02G](../../sprints/sprint-02/checkpoint-02g-plan.md), incluidas implementación, pruebas y aplicación local de una migración vacía.

## Contexto

§36 identifica Merchant como raíz comercial y Branch como sucursal dependiente de comercio/mercado. No concreta los estados iniciales de persistencia, tratamiento/formato/unicidad de tax_id ni niveles de risk_level. Los estados/acciones operativos de las pantallas T-03/A-06 no constituyen autorización para elegir silenciosamente un enum o activar comercios. DP-001 y demás decisiones productivas siguen abiertas.

La base geográfica de ADR-006 y la creación fija de 02F están aprobadas/publicadas. Se necesita preparar relaciones del siguiente bloque Marketplace sin ampliar sus casos de uso ni datos de desarrollo.

## Decisión

Marketplace es dueño privado de merchants y branches. Una migración aditiva/transaccional crea ambas tablas vacías, bigint interno/ULID público válido y único, timestamps UTC y version>=1. Status admite únicamente draft. No agrega modelos, puertos o API sin uso ni consulta tablas privadas de Identity.

Merchants conserva legal_name/trade_name requeridos y no vacíos tras btrim; tax_id varchar(64) y risk_level varchar(40) admiten exclusivamente NULL, sin evaluación de riesgo ni identificación fiscal. Esa presencia refleja campos del maestro; sus límites son técnicos provisionales, no una política fiscal/productiva. No hay unique de nombres ni tax_id.

Branches tiene exactamente un merchant_id y market_id requeridos, con FK ON DELETE RESTRICT a tablas del mismo módulo. Merchant no recibe market_id: puede tener sucursales en distintos mercados, y varias en uno mismo, sin unicidad merchant/market. Name y timezone son explícitos/requeridos, sin herencia/default de timezone. Point geography(Point,4326) es requerido, válido/no vacío/2D, finito en rangos WGS84, con GIST. Índices btree (merchant_id, market_id) y (market_id) cubren alcances/FK; ambos public_id tienen unique/index.

No deleted_at ni historial de transición sin caso de uso de mutación. Down rechaza destrucción automática; eliminar/revertir datos requiere un plan revisado. No seeds, backfill, membresías, grants ni activación. Fixtures positivos solo en bases temporales propias; desarrollo conserva seis tablas Marketplace vacías y los datos previos de Identity/Platform.

## Integridad espacial

ST_IsValid(point::geometry, 0) evita NOTICE de validación con detalles espaciales. No vacío, dimensiones y rangos se comprueban adicionalmente. Algunos literales no finitos son rechazados por el parser PostGIS antes del CHECK; ambos caminos deben impedir persistencia, sin imprimir SQL/bindings/valores.

El cast a geography puede normalizar entradas fuera de rango. Las restricciones verifican lo almacenado; no afirman que detecten el valor original previo al cast. Un futuro límite de escritura deberá usar GeographicPoint para validar longitud/latitud originales antes del cast. 02G no ofrece entrada geográfica por API/consola ni importación.

## Alternativas consideradas

Posponer todas las relaciones deja el siguiente bloque sin base verificable. Definir ahora estados operativos, fiscalidad y riesgo introduce reglas no concretadas en el maestro. Se aprobó una base vacía restrictiva, sin habilitar esas operaciones; las ampliaciones futuras exigirán especificación y migración compatibles hacia adelante.

## Consecuencias y límites

Las pruebas verifican FK/ULID/checks/tipos/índices, multi-comercio/mercado, geografía nativa, preservación de datos legados y segunda migración sin cambios. Las aserciones previas de ausencia de merchants/branches se reemplazan por presencia vacía e independencia explícita; las sondas 02A–02E y fixtures 02F no las consultan ni modifican.

No hay contratos/comisiones/documentos, horarios/cobertura por sucursal, configuración, personal, catálogo/stock, discovery/checkout ni API comercial. No se sustituye una operación real abierto/pausado/aprobación por draft. Una futura mutación administrativa necesitará capacidad/alcance/recurso del servidor, MFA sensible, correlación, idempotencia/concurrencia, historial/auditoría y outbox si publica eventos, conforme al maestro.

La aprobación de DP-032 no autoriza commit/push/CI ni despliegue productivo; la publicación del checkpoint se solicitará con [evidencia terminada](../../sprints/sprint-02/checkpoint-02g-evidence.md).
