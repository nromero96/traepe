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

## Ejercicio de cobertura local — 02A

El usuario aprobó un ejercicio ficticio previo a resolver DP-001. DP-027 fija WGS84/SRID 4326, longitud/latitud, bordes incluidos, máxima prioridad y rechazo por empate máximo entre zonas distintas. La selección se prueba con polígonos sintéticos, sin persistir mercados ni áreas reales. El resultado no indica sucursal abierta, disponibilidad, precio ni ETA; las reglas operativas descritas arriba continúan pendientes de su implementación autorizada. Véanse [decisión](../architecture/decisions/pending-decisions.md#dp-027--selección-geoespacial-del-ejercicio-local) y [alcance](../sprints/sprint-02/README.md).

02B adapta el mismo ejercicio a una sonda HTTP de lectura pública local/testing, siguiendo el descubrimiento del visitante del maestro §58. No amplía las reglas de selección ni constituye la resolución operativa de mercado. El [contrato técnico](../api/README.md#ampliación-local-de-marketplace--02b) limita entrada/salida, errores, privacidad, entorno y tasa de solicitudes.

02C prepara una base persistente vacía: countries, markets y service_zones pertenecen a Marketplace, con mercados/zonas solo draft y zone_type solo fixture. DP-028 aprobó explícitamente estas restricciones locales; [ADR-006](../architecture/decisions/ADR-006-marketplace-geographic-foundation.md) y el [plan de datos](../sprints/sprint-02/checkpoint-02c-plan.md) documentan el alcance y su trazabilidad con §§32–34 y 54–57. No hay seeds ni selección de borradores por las sondas. DP-001 sigue abierta y las reglas operativas anteriores requieren implementación y decisiones posteriores.

02D incorpora un diagnóstico nuevo de consola local/testing, aprobado mediante DP-029. Lee únicamente el mercado draft indicado por ULID y sus zonas draft/fixture; usa geography nativa, bordes incluidos, máxima prioridad y rechazo de empate máximo. ST_Covers se complementa con ST_DWithin de distancia 0 para conservar bordes de huecos; no se introduce buffer ni distancia positiva. Market_not_found distingue mercado ausente de outside sin coincidencias. Selected describe únicamente el ejercicio técnico y no autoriza servicio ni checkout. Se conserva la selección inline de 02A/02B, el esquema draft/fixture y el bloqueo operativo DP-001. Véase [plan aprobado de 02D](../sprints/sprint-02/checkpoint-02d-plan.md).
