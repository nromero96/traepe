# Operación, administración y criterios del núcleo

**Origen:** secciones 7, 9–16 del maestro v1.6.

## Tienda

La consola debe recibir pedidos con alerta y SLA, aceptar/rechazar, ajustar preparación, administrar sustituciones, marcar hitos, pausar sucursal/productos y consultar incidencias. La administración cubre sucursales, catálogo, inventario, permisos, promociones, reportes y liquidaciones.

## Administrador maestro

La interfaz `admin-dashboard` será exclusivamente para administración maestra: operación, comercios, logística, clientes, finanzas, marketing, configuración y auditoría. El backend que sirve estas capacidades residirá en `apps/api`.

## Logística

El repartidor pasa por registro/aprobación, disponibilidad, oferta, recojo, tracking, entrega y conciliación. La asignación filtra elegibilidad y puntúa distancia, ETA, preparación, aceptación, costo y riesgo; registra todos los candidatos y resultados.

## Recuperación

Tienda sin respuesta, falta de stock, pago incierto, falta de repartidores, cancelación de courier, cliente ausente, dirección errónea, daño y caída de proveedor requieren tratamiento idempotente, evidencia y trazabilidad.

## Criterios del núcleo

- Sucursales independientes en precios, stock, horarios y cobertura.
- Checkout recalculado por servidor.
- Sin duplicados por reintentos.
- Economía e instantánea histórica completas.
- Estados de pedido, pago, preparación, entrega, incidencia y liquidación separados.
- Intervenciones con actor, motivo y antes/después.
- API común para interfaces actuales y apps futuras.
- Eventos suficientes para reconstrucción y analítica.
