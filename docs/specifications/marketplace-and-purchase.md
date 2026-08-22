# Marketplace y experiencia de compra

**Origen:** secciones 3–8 del maestro v1.6.

## Estructura comercial

La plataforma contiene comercios; cada comercio puede tener múltiples sucursales con ubicación, horario, inventario, cobertura, preparación y disponibilidad propios. Contratos y comisiones pueden heredarse o configurarse por sucursal.

## Entrega

Modalidades: logística trae.pe, logística del comercio y recojo. La modalidad del pedido es inmutable tras su aceptación salvo intervención administrativa auditada.

## Descubrimiento

La dirección se normaliza y geocodifica, se valida contra mercado/zona y determina sucursales abiertas con cobertura. Ordenamiento: disponibilidad, relevancia, distancia, calidad, campañas y ETA. Los resultados respetan horarios, zona, stock y restricciones.

## Carrito y cotización

El carrito pertenece a una sucursal. Conserva producto, variante, modificadores, cantidad e instrucciones. El servidor recalcula stock, cobertura, promociones, precios y total antes del pago. Cambios de precio requieren aceptación y productos restringidos requieren control de edad.

## Pedido

Flujo: cotización, creación, pago, envío a tienda, aceptación, preparación, asignación, recojo, traslado, entrega y cierre. El pedido es una transacción orquestada; avanza mediante comandos validados y eventos auditables.

## Economía

Cada cotización/pedido conserva desglose de productos, descuentos y financiador, logística, cargos al cliente, comisiones y liquidación. Los ítems guardan una instantánea inmutable; cambios del catálogo no alteran pedidos históricos.
