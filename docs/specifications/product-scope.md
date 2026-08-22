# Producto, alcance y actores

**Origen:** secciones 1, 2, 12, 15–17 del maestro v1.6.

trae.pe conectará clientes, comercios, sucursales y repartidores en un marketplace peruano multicategoría. Tienda Siete puede ser piloto, pero el núcleo no puede quedar acoplado a licores.

## Objetivos

- Descubrimiento por ubicación, compra y seguimiento en tiempo real.
- Gestión de sucursales, catálogo, disponibilidad, pedidos, promociones y liquidaciones.
- Logística de plataforma y, cuando aplique, del comercio o recojo.
- Datos operativos trazables para analítica e IA futura.
- Seguridad, velocidad y escalabilidad al crecer mercados y volumen.

## Actores

Visitante, cliente, comercio, administrador de tienda, operador de sucursal, repartidor, operación trae.pe, finanzas, administrador maestro y servicios externos.

La identidad autenticable es central y puede tener múltiples perfiles. La autorización se evalúa por rol/capacidad, alcance y recurso; las acciones sensibles se auditan.

## Límites iniciales

- Carrito de una sola sucursal.
- Asignación logística inicial basada en reglas y puntuaciones configurables.
- Apps móviles nativas posteriores; la API y eventos deben quedar preparados.

## MVP

Incluye identidad cliente, cobertura, comercios/sucursales, catálogo/stock, carrito y pedido, pagos/webhooks, interfaces de tienda y administración, tres modalidades logísticas, tiempo real básico, incidencias, reembolsos, liquidaciones y auditoría.

No incluye inicialmente carrito multitienda, ML para despacho, publicidad automatizada, billetera/crédito avanzado ni apps móviles nativas.

## Decisiones sin cerrar

Mercado inicial, esquema de cobros, flota, proveedores y modalidades de pago, facturación, cancelaciones, verificación de edad, SLA y soporte. Se controlan en [decisiones pendientes](../architecture/decisions/pending-decisions.md).
