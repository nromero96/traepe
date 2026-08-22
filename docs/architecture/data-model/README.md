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
