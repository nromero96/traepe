# Catálogo local y primer producto conceptual — 03A

Fuente: maestro v1.6 §§36–37, 49–50, 52–60; [DP-035](../architecture/decisions/pending-decisions.md#dp-035--catálogo-local-con-primer-producto-conceptual), [ADR-008](../architecture/decisions/ADR-008-catalog-local-draft-foundation.md) y [plan aprobado](../sprints/sprint-03/checkpoint-03a-plan.md).

## Alta

Un actor activo con sesión y permiso create específico envía merchant_public_id, catalog {name} y product {name, description, brand}. Todas las propiedades son requeridas; description/brand admiten NULL. El servidor rechaza propiedades adicionales, IDs/estados/versiones del cliente, textos inválidos y claves de idempotencia incorrectas.

Marketplace confirma y bloquea exclusivamente el comercio draft cuyo diario 02I pertenece al actor. Comercio ajeno, ausente o ajeno al ejercicio 02I devuelve merchant_not_available indistinguible. No existe búsqueda pública ni administración operativa.

Una transacción crea catálogo, primer producto conceptual, diario cifrado inmutable y claim de idempotencia. Ambos perfiles son draft/version 1; product_type permanece NULL. Las reglas completas de texto, FK, auditoría y concurrencia están en ADR-008 y el plan.

## Consulta y repetición

GET consulta el snapshot original del creador con permiso read independiente. No expone IDs internos, autor, hashes o datos Identity; operaciones ajenas o inexistentes devuelven el mismo not_found tras autorización. El resultado no cambia al modificar perfiles o deleted_at.

POST con igual clave, actor, alcance y entrada exacta devuelve la operación original con create vigente. Cambiar la entrada devuelve 409; una clave vencida no se reutiliza. Claves diferentes permiten catálogos/productos con nombres iguales. No se normalizan nombres, NULL ni descripción vacía.

## Límites del bloque

Solo ficticio local/testing; no SKU, precios, stock, categorías, restricciones favorables, medios, listings, publicación, disponibilidad o venta. Producto conceptual no es unidad vendible. No frontend/dependencias nuevas ni grants/usuarios de desarrollo. DP-001/DP-002/DP-007/DP-012 siguen abiertas; MFA sigue siendo obligatorio para administración sensible real.

[Contrato API](../api/README.md#alta-y-consulta-de-catálogo-ficticio--03a--openapi-170) y [evidencia](../sprints/sprint-03/checkpoint-03a-evidence.md).
