# Modelo de datos

**Origen:** secciones 31–50 del maestro v1.6.

## Principios

- PostgreSQL transaccional y PostGIS para puntos, polígonos, distancias y cobertura.
- bigint interno y ULID/UUID público no secuencial.
- Importes enteros más `currency_code`; nunca `float`.
- Tiempos UTC y timezone de mercado separado.
- JSONB solo para snapshots/payloads/metadatos versionados.
- Finanzas, auditoría y eventos append-only.
- Base compartida multicomercio; scoping por `merchant_id`, `branch_id` y autorización.

## Dominios persistentes

Geografía/mercados; identidad y permisos; comercios/sucursales; catálogo; inventario; carrito/cotización/promociones; pedidos y snapshots; pagos; logística/tracking; soporte/reputación; ledger/liquidaciones; outbox/inbox/auditoría.

## Integridad

Foreign keys para datos transaccionales, checks de montos/rangos/estados, uniques compuestos para IDs externos y reservas, y optimistic locking en Order, Delivery, InventoryItem y Settlement. Soft delete solo para catálogos/perfiles recuperables.

## Migraciones futuras

Oleadas 00 Platform, 01 Identity, 02 Marketplace, 03 Catalog, 04 Inventory, 05 Pricing, 06 Ordering, 07 Payments, 08 Logistics, 09 Finance, 10 Operations y 11 Projections. Primero agregar, luego desplegar/migrar y finalmente retirar; evitar bloqueos o renombres largos en una operación.

El detalle de tablas, campos, cardinalidades, agregados e índices permanece en las matrices 34–50 del maestro.

## Directorio de Identity — 01C

La representación técnica de roles/capacidades y asignaciones/excepciones de §35 se materializa en identity_roles, identity_permissions, identity_role_permissions, identity_role_assignments e identity_permission_grants. Referencias públicas ULID, claves internas bigint, scope exacto y efectos/expiry conforme a DP-026. Las tablas se crean vacías; no hay seeds ni mutaciones administrativas. Véase [evidencia de 01C](../../sprints/sprint-01/checkpoint-01c-evidence.md). Scopes de otros módulos se validarán por contratos del dueño del recurso; no se consultan tablas privadas cruzadas.

## Base geográfica vacía — 02C

DP-028 y [ADR-006](../decisions/ADR-006-marketplace-geographic-foundation.md) aprobaron countries, markets y service_zones privadas de Marketplace, inicialmente vacías, mercados/zonas solo draft y zone_type solo fixture. La migración aditiva aplicada localmente conserva geography(MultiPolygon,4326) de §56, requerido/válido/no vacío/2D e índice GIST; bigint interno, ULID público válido/único, timestamps UTC, FK restrict e índices de referencia. País exige código único de dos letras mayúsculas; monedas tres letras mayúsculas. Timezone y moneda de mercado son explícitos; version>=1 y priority entero con signo.

El [plan aprobado](../../sprints/sprint-02/checkpoint-02c-plan.md) detalla campos/checks y límites; la [evidencia de 02C](../../sprints/sprint-02/checkpoint-02c-evidence.md) registra pruebas y aplicación sin resetear desarrollo. No hay seeds, administración ni selección de borradores por las sondas. Estas restricciones provisionales no definen estados/tipos operativos; DP-001 sigue bloqueando activación real. La migración es forward-only: eliminar datos requiere un plan revisado.

## Lectura diagnóstica persistida — 02D

DP-029 autoriza un nuevo diagnóstico de consola local/testing sobre un mercado draft indicado por ULID y sus zonas draft/fixture. La consulta restringe por FK y usa un snapshot de una sentencia. No modifica el esquema ni escribe registros. Las tablas de desarrollo siguen vacías; los fixtures persistidos se insertan solo en bases temporales propias de pruebas. Selected es coincidencia técnica, sin elegibilidad operativa. Las sondas inline anteriores no consultan borradores. El [plan de 02D](../../sprints/sprint-02/checkpoint-02d-plan.md) documenta geography nativa y la comprobación adicional de distancia cero para incluir bordes de huecos en PostGIS 3.5.7.

## Registro append-only de fixtures — 02F

DP-031 aprobada agrega marketplace_local_fixture_operations, privada de Marketplace y vacía en desarrollo: bigint interno, public_id ULID válido/único; market_id FK restrict único; actor_public_id del contrato público Identity, sin FK ni consulta sobre users; profile_version cerrado A/B y schema_version=1; hashes SHA-256 de key/request, correlation_id original, snapshot jsonb y created_at timestamptz UTC. Índices para actor y creación; trigger propio rechaza cualquier UPDATE/DELETE. Sin updated_at, soft delete, clave cruda o entrada arbitraria. La migración aditiva es forward-only y preservó los conteos existentes.

El callback crea/reutiliza únicamente country ZZ si sus datos coinciden, un mercado draft y tres zonas draft/fixture con ULIDs nuevos de servidor, usando los WKT sintéticos 02A convertidos a MultiPolygon geography SRID 4326. País incompatible rechaza sin sobrescribir. Claim, conjunto e historial se confirman juntos; fallos parciales revierten todos los registros nuevos y conservan el país preexistente. El replay usa el snapshot validado/inmutable de la operación del actor, sin reconstruirlo desde las filas geográficas mutables. [Plan con campos y política](../../sprints/sprint-02/checkpoint-02f-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02f-evidence.md). No hay seeds ni activación real; DP-001 sigue abierta.

## Comercios y sucursales vacíos — 02G

Fuente: maestro v1.6 §§3.1, 32–33, 36 y 54–56; [DP-032 aprobada](../decisions/pending-decisions.md#dp-032--base-local-vacía-de-comercios-y-sucursales) y [ADR-007](../decisions/ADR-007-marketplace-commercial-foundation.md). Merchants y branches privadas de Marketplace, bigint interno/ULID público válido/único, timestamps UTC requeridos y version>=1. Ambas admiten únicamente draft. Merchant conserva legal_name/trade_name requeridos/no vacíos tras btrim; tax_id varchar(64) y risk_level varchar(40) solo NULL, sin habilitar fiscalidad ni evaluación de riesgo.

Branch tiene FK restrict a Merchant y Market, name/timezone explícitos/requeridos, geography(Point,4326) válida/no vacía/2D y finita en rangos WGS84. GIST(point), btree (merchant_id, market_id) y (market_id), además del unique public_id. Merchant no tiene market_id; no se inventa unicidad de nombres ni pareja merchant/market. No deleted_at, membresías, configuración, documentos, contratos o historial de transiciones sin caso de uso.

La migración es aditiva/transaccional/forward-only; datos positivos solo en bases temporales propias. Desarrollo mantiene seis tablas Marketplace vacías y los datos Identity/Platform previos. La geografía comprueba lo almacenado; un futuro límite de escritura deberá validar coordenadas originales antes del cast, que puede normalizar valores fuera de rango. [Plan con esquema exacto](../../sprints/sprint-02/checkpoint-02g-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02g-evidence.md).

02H, aprobado por DP-033, verifica la relación exacta Branch → Merchant/Market mediante tres ULID explícitos y estados draft en una sola lectura. No modifica esquema, constraints, filas o política de borrado; no interpreta point como cobertura ni consulta zonas. Solo devuelve coincidencia técnica, sin datos de perfiles o ubicación. Desarrollo sigue vacío y los casos positivos viven exclusivamente en bases temporales propias. [Plan](../../sprints/sprint-02/checkpoint-02h-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02h-evidence.md).

## Diario cifrado de altas comerciales ficticias — 02I

DP-034 aprobada agrega marketplace_local_commerce_operations privada y vacía: bigint id, public_id ULID unique, merchant_id y branch_id bigint FK restrict unique, market_id FK restrict/index; actor_public_id ULID/index del contrato público Identity sin FK cruzada; schema_version=1, key_hash/request_hash/response_hash SHA-256 válidos, correlation_id ULID, response_snapshot_ciphertext text no vacío, created_at timestamptz UTC/index. No updated_at/deleted_at; trigger rechaza UPDATE/DELETE y down es forward-only.

El snapshot cerrado v1 contiene IDs públicos y datos originales de Merchant/Branch draft/version=1, sin bigint, fiscalidad/riesgo ni datos privados Identity. Crypt cifra todo el snapshot con la clave ignorada de Laravel; hash de plaintext y estructura se verifican al leer. Claim, merchant, branch e historial se confirman atómicamente; Platform conserva solo hashes/referencia. Esta API valida coordenadas originales antes del cast y nombres exactos sin controles, conservando las restricciones base de ADR-007. No define retención ni tratamiento de datos personales reales. Migración vacía aditiva y positivos solo en bases temporales propias; desarrollo conserva siete tablas Marketplace vacías. [Plan](../../sprints/sprint-02/checkpoint-02i-plan.md), [ADR-007](../decisions/ADR-007-marketplace-commercial-foundation.md) y [evidencia](../../sprints/sprint-02/checkpoint-02i-evidence.md).

## Catalog local vacío — 03A

[ADR-008](../decisions/ADR-008-catalog-local-draft-foundation.md) concreta catalogs/products/catalog_local_draft_operations privadas de Catalog. Bigint interno y public_id ULID único; catálogo ligado a merchant_public_id con FK restrict pública, producto ligado por catalog_id bigint privado. Nombres/brand válidos UTF-8 de hasta 255 caracteres y description nullable hasta 4000, LF permitido; draft/version>=1 y product_type exclusivamente NULL. Timestamps UTC; deleted_at nullable inicialmente NULL y >=created_at, sin API de borrado.

Diario cifrado v1 append-only con hashes SHA-256, actor/merchant/correlación públicos, created_at e índices; sin updated_at/deleted_at ni FK a users. FK compuestas catálogo/comercio y producto/catálogo impiden relaciones cruzadas. Referencias públicas, criterios/checks/índices completos y migración aditiva forward-only están en el [plan aprobado](../../sprints/sprint-03/checkpoint-03a-plan.md). Solo tres tablas vacías en desarrollo; no SKU/listings/precios/stock ni datos operativos.

## Diario de productos adicionales y revisión del catálogo — 03B

[DP-036](../decisions/pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual)/[ADR-008](../decisions/ADR-008-catalog-local-draft-foundation.md) aprueban catalog_local_draft_product_operations privada de Catalog: bigint/ULID; catálogo y producto FK restrict propios, producto unique; merchant/actor públicos; schema_version=1; revisión anterior positiva menor al máximo integer y posterior=anterior+1; unique(catalog_id,catalog_version_after), hashes SHA-256/correlación ULID/ciphertext no vacío y created_at UTC. FK compuestas catálogo/comercio y producto/catálogo usan los unique existentes, impidiendo referencias cruzadas. Sin FK a Identity, updated_at o deleted_at; trigger UPDATE/DELETE denegados y down forward-only.

Una migración añade solo esa tabla vacía y products_local_draft_page_index parcial sobre (catalog_id,id) para status draft/deleted_at NULL. No migra datos o renombra constraints previas. La aplicación conserva el diario inicial 03A: nuevas altas actualizan Catalog.version/updated_at e insertan Product/diario adicional en una transacción idempotente. Texto/draft/type NULL/product version 1 sin cambios. GET contenido usa la revisión actual; los diarios originales no se reconstruyen desde perfiles.

[Plan](../../sprints/sprint-03/checkpoint-03b-plan.md) detalla campos, índices y reglas; [evidencia](../../sprints/sprint-03/checkpoint-03b-evidence.md) registra la validación/aplicación. Desarrollo debe conservar once tablas Marketplace/Catalog vacías; positivos solo en bases temporales propias. Datos productivos, SKU/precios/stock/listings siguen fuera de alcance.

## Diario de edición de productos draft — 03C

[DP-037](../decisions/pending-decisions.md#dp-037--edición-local-de-productos-draft)/[ADR-008](../decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03c) aprueban catalog_local_draft_product_changes: bigint/ULID; catalog_id/product_id FK restrict propias; merchant/actor públicos; schema_version=1; pares de revisiones catálogo/producto anteriores positivos menores al máximo integer y posteriores=anteriores+1; key/request/response hashes SHA-256, correlation_id ULID, response_snapshot_ciphertext no blanco y created_at UTC.

FK compuestas catálogo/comercio y producto/catálogo impiden referencias cruzadas. Unique catálogo/revisión y producto/revisión permiten múltiples ediciones por producto; índices cubren FK y public_id cubre GET exacto con filtro de actor. No índice nuevo de products o índices preventivos. Trigger rechaza UPDATE/DELETE, sin updated_at/deleted_at o FK Identity; down forward-only. Snapshot cerrado antes/después cifrado/hash/metadata verificados. La secuencia entre diarios de alta/cambio se serializa por lock/versión del catálogo, sin unique entre tablas distintas.

Una tabla inicialmente vacía aditiva, sin modificar constraints/filas previas. PUT cambia únicamente tres textos/version/updated_at del producto y version/updated_at del catálogo; los datos históricos originales se conservan. [Plan completo](../../sprints/sprint-03/checkpoint-03c-plan.md).

## Variantes draft y diario de alta — 03D

[DP-038](../decisions/pending-decisions.md#dp-038--variantes-draft-locales)/[ADR-008](../decisions/ADR-008-catalog-local-draft-foundation.md#ampliación-aprobada--03d) aprobados mediante «si apruebo». [Plan completo](../../sprints/sprint-03/checkpoint-03d-plan.md) define dos tablas privadas Catalog vacías, inicialmente draft y forward-only: product_variants y catalog_local_draft_variant_operations.

Variant tiene bigint/ULID, FK compuestas propias catálogo/comercio y producto/catálogo, seis campos descriptivos, timestamps UTC/soft-delete y versión 1. SKU único por comercio sin condición de borrado; índice product_id,id para keyset/FK e índice catalog_id para FK. Attributes JSONB objeto con función CHECK propia immutable de forma/cardinalidad/claves/strings; no relación principal o valor numérico operativo.

Diario tiene variante única y FK variant_id,product_id, más FK producto/catálogo y catálogo/comercio; ULIDs actor/merchant sin FK Identity, pares de revisiones consecutivos/unique por catálogo y producto, hashes/correlación/ciphertext y created_at UTC. Trigger rechaza UPDATE/DELETE. Snapshot original conserva producto/catálogo/variante y se valida contra metadata, sin reconstrucción mutable.

Una alta confirma variante/diario y versiones catálogo/producto +1 junto al claim; no modifica textos/identidad/estado. Migración aditiva conserva las 35 tablas anteriores y agrega exactamente dos vacías, con segundo migrate sin cambios; sin seeds/reset/rollback de desarrollo. No precio, inventario, listings o tablas de otros módulos.
