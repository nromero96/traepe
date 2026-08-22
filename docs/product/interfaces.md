# Interfaces y navegación

**Origen:** secciones 66–77 y 82–96 del maestro v1.6.

## Productos

- `customer-web`: ubicación, discovery, tienda/producto, carrito, checkout, pedidos, tracking, soporte y cuenta.
- `merchant-dashboard`: acceso, consola de pedidos, catálogo/inventario, sucursales, personal, promociones, finanzas e incidencias.
- `admin-dashboard`: interfaz exclusiva del administrador maestro para operación global, comercios, logística, soporte, finanzas, marketing, configuración y auditoría.
- `apps/api`: backend maestro compartido por las interfaces; no es una interfaz visual.

## Reglas de experiencia

No mostrar disponibilidad sin dirección; precios y totales vienen del servidor; estados críticos son persistentes; acciones irreversibles explican consecuencia y requieren confirmación. Toda pantalla contempla loading, vacío, error, sin conexión, permiso insuficiente, conflicto y reintento seguro.

## Pantallas críticas

Cliente: ubicación/inicio, tienda/producto/carrito, checkout, resultado/tracking/incidencia. Tienda: acceso/inicio, bandeja y detalle de pedido, catálogo/inventario, operación/personal/finanzas. Administrador: centro de operación, pedidos, detalle/intervención, comercios, logística, soporte, pagos, ledger, liquidaciones, promociones, mercados, políticas y auditoría.

Las fichas completas de campos, validaciones y estados alternos permanecen en las matrices 83–95 del maestro.
