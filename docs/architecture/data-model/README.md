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
