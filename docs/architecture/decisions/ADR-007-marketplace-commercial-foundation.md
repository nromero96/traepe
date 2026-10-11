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

## Continuidad aprobada — 02H

Después de publicar 02G, el usuario aprobó DP-033 y el [plan 02H](../../sprints/sprint-02/checkpoint-02h-plan.md) mediante «Apruebo implementarlo» el 10 de octubre de 2026. La ampliación agrega componentes con consumidor concreto: un diagnóstico de consola local/testing de solo lectura por tres public_id estrictos. Una sentencia privada de Marketplace exige relación Branch/Merchant/Market exacta y estado draft de las tres entidades; devuelve únicamente coincidencia booleana, transformada en versión técnica y matched/not_found.

No modifica el esquema ni los límites de la base 02G. Sin HTTP, modelos, escrituras, datos/grants en desarrollo, membresías o contrato de acceso para otro módulo. Comando/puerto registrados solo en local/testing y guards antes de DI/SQL, sin datos de perfil/ubicación/fiscalidad en salida. Matched no es autorización ni estado operativo. Fixtures positivos solo en bases temporales propias; las sondas anteriores siguen independientes. [Evidencia](../../sprints/sprint-02/checkpoint-02h-evidence.md). La aprobación de implementación reserva commit/push/CI al cierre.

## Continuidad aprobada — 02I

DP-034 y el [plan 02I](../../sprints/sprint-02/checkpoint-02i-plan.md) fueron aprobados mediante «si apruebo» el 10 de octubre de 2026. POST local/testing crea Merchant y primera Branch draft en un Market draft existente. Nombres UTF-8 explícitos de 1–255 caracteres, no vacíos tras btrim ASCII, sin controles U+0000–001F/U+007F; se preservan exactamente y pueden repetirse. GeographicPoint valida números JSON finitos originales antes del cast a geography; timezone requerida Etc/UTC ficticia. Servidor genera ULIDs y fija draft/version=1 y tax_id/risk_level=NULL. No amplía estados, fiscalidad/riesgo o configuración.

Sesión/CSRF y capacidades create/read independientes con scope/recurso técnicos del servidor; GET exige read vigente y propiedad del creador, devolviendo el snapshot original exclusivo. Idempotencia por actor/scope/key durante 24h, callback reautorizado y mercado bloqueado: claim, Merchant, Branch y diario cifrado se confirman juntos o se revierten juntos. Replay exige create vigente y conserva datos/TTL; no exige read para responder su POST. Sin evento público o efecto externo, no hay outbox sin consumidor.

Una migración aditiva agrega marketplace_local_commerce_operations vacía: FK restrict propias, identificadores/hashes válidos, snapshot cifrado con Crypt y hash verificado, schema_version=1, actor/correlación y created_at UTC. Trigger append-only rechaza UPDATE/DELETE y down es forward-only. Desarrollo conserva siete tablas Marketplace vacías, sin usuarios/grants nuevos; positivos solo en bases temporales propias. No registro real, operación sensible administrativa ni excepción a MFA; esa administración sigue requiriendo MFA conforme al maestro §60. DP-001/DP-012 siguen abiertas. [Evidencia](../../sprints/sprint-02/checkpoint-02i-evidence.md); commit/push/CI requieren aprobación final.
