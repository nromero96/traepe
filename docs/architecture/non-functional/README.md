# Requisitos no funcionales

**Origen:** secciones 12, 48 y 63–64 del maestro v1.6.

## Requisitos

- **Rendimiento:** baja latencia común, trabajos pesados fuera de request y caché controlada.
- **Escalabilidad:** módulos desacoplados, colas y procesos horizontales.
- **Disponibilidad:** reintentos, circuit breakers, monitoreo y modo degradado.
- **Consistencia:** transacciones, idempotencia, outbox e integridad concurrente.
- **Observabilidad:** logs JSON, métricas, trazas y correlación por pedido.
- **Compatibilidad:** API versionada para PWA, dashboards y apps futuras.
- **Recuperación:** PITR, restauración probada y runbooks.

## Calidad

Pruebas unitarias, feature, contratos, integración y carga para discovery, catálogo, checkout, bandeja y tracking. Un módulo crítico debe poder probarse sin HTTP ni proveedor real.

## Caché y lecturas

PostGIS para tiendas cercanas; read models para bandejas/catálogos; Redis/CDN con invalidación por versión; FTS/`pg_trgm` inicialmente. Caché nunca es fuente de verdad para precio final, pago, stock comprometido ni estados.

## Aceptación

Reintentos producen un único efecto; mutaciones críticas generan historia/outbox/correlación; no hay dinero float; scopes de comercio/sucursal se verifican; fallas de búsqueda/notificaciones no pierden pedidos ni pagos.
