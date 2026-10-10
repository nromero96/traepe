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
