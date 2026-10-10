# Marketplace y experiencia de compra

**Origen:** secciones 3–8 del maestro v1.6.

Las concreciones técnicas locales al final también trazan §§32–34.1 y 58–60, con sus decisiones aprobadas. No sustituyen las reglas operativas del maestro.

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

02E adapta exclusivamente ese diagnóstico persistido a HTTP local/testing, conforme a DP-030 aprobada mediante «aprobar y continuar». Exige sesión cookie y permiso técnico exacto en scope platform sintético, con recurso resuelto por servidor y DP-026 íntegra. El permiso permite consultar cualquier mercado draft solicitado, pero cada lectura se limita a ese mercado y sus zonas draft/fixture. No es cobertura pública ni autorización operativa por mercado. No concede usuarios/permisos en desarrollo ni modifica borradores; fixtures positivos solo en bases temporales propias. Conserva las sondas anteriores y DP-001 abierta. [Plan](../sprints/sprint-02/checkpoint-02e-plan.md) y [contrato API](../api/README.md#diagnóstico-de-borradores-persistidos-autorizado--02e).

## Ejercicio local de creación geográfica — 02F

Fuente: maestro v1.6 §§33–34.1, 49–50, 55–56 y 58–60, concretados por DP-031 aprobada explícitamente mediante «aprobado, continuar.» el 10 de octubre de 2026. POST local/testing crea únicamente synthetic-origin-a-v1 o synthetic-origin-b-v1: país ZZ/Synthetic Country/ZZZ compartido si coincide; mercado nuevo Synthetic Market A/B, Etc/UTC, XXX, draft/version=1; tres zonas Synthetic Zone A/B/C, prioridades 10/20/20, draft/fixture y WKT sintéticos del ejercicio 02A. Los IDs los genera el servidor. País incompatible rechaza; no se aceptan coordenadas, nombres, estados, moneda o configuración arbitrarios.

La capacidad create técnica independiente de read, sesión/CSRF, idempotencia 24 horas por scope/actor/key, snapshot append-only y rollback/concurrencia son parte de la especificación aprobada. [Plan con política exacta](../sprints/sprint-02/checkpoint-02f-plan.md) y [evidencia](../sprints/sprint-02/checkpoint-02f-evidence.md). Desarrollo mantiene vacías las tablas y no recibe grants; creaciones positivas solo en bases temporales de pruebas. Selected sigue siendo un resultado diagnóstico. No autoriza edición, importación, activación, administración ni operación real; DP-001 permanece abierta.

## Base local de comercios y sucursales — 02G

Fuente: maestro v1.6 §§3.1, 9, 32–34, 36, 52–56, 60, 72 y 78–79; DP-032 aprobada mediante «Apruebo» el 10 de octubre de 2026. Dos tablas privadas vacías de Marketplace: Merchant como raíz comercial, Branch con FK restrict a su Merchant y Market. Varias sucursales/comercios por mercado y un comercio con sucursales en diferentes mercados, sin unicidades de nombre o herencias añadidas. ULID público/bigint interno, timestamps UTC, version>=1 y solo draft. Tax_id/risk_level exclusivamente NULL; nombres requeridos y Branch con timezone explícito y point geography(Point,4326), válido/no vacío/2D/finito, con GIST.

La concreción local no redefine abierto/pausado de T-03 ni aprobar/observar/suspender/RUC/riesgo de A-06. Esas reglas operativas, su autorización administrativa y activación requieren especificación posterior. La migración aditiva/forward-only no crea registros ni permisos en desarrollo; casos positivos solo en bases temporales propias. No hay API, onboarding, membresías, contratos, horarios, geocodificación, catálogo ni stock. Las sondas geográficas y creación fija 02F permanecen independientes. [Plan](../sprints/sprint-02/checkpoint-02g-plan.md), [ADR-007](../architecture/decisions/ADR-007-marketplace-commercial-foundation.md) y [evidencia](../sprints/sprint-02/checkpoint-02g-evidence.md). DP-001 sigue abierta.
