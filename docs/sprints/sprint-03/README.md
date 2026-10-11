# Sprint 03 — Catálogo local del comercio

Fuente: maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79. El maestro distingue catálogo del comercio, producto conceptual, variante vendible y publicación por sucursal. Este sprint inicia el flujo de catálogo sobre la base comercial publicada en 02I.

## Base comprobada

02I publicado en main 0ab976d25c7e1c3993bc712ab437b5257ee4eed0, [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38098790155). Run 38098790155/job 114350184797 verificados sobre ese SHA: build/migración, calidad/integración, reinicio/persistencia y limpieza correctos. 255 pruebas locales y 5892 aserciones; siete tablas Marketplace vacías, ocho servicios saludables y datos anteriores preservados. [Evidencia de 02I](../sprint-02/checkpoint-02i-evidence.md).

## Checkpoint 03A — Alta y consulta de catálogo con primer producto

El usuario solicitó continuar con la recomendación de catálogo. El [plan 03A](checkpoint-03a-plan.md) autoriza un bloque funcional completo: POST crea catálogo y primer producto conceptual draft para un comercio ficticio creado por el mismo actor en 02I; GET consulta su snapshot original con permiso read independiente. Incluye persistencia, contrato público Marketplace, sesión/CSRF, idempotencia, auditoría cifrada y pruebas HTTP/DB reales.

DP-035 fue resuelta mediante «Aprobar plan completo 03A (recomendado)» el 10 de octubre de 2026. [ADR-008](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md) documenta las reglas aprobadas de datos/estados/borrado, propiedad, permisos, referencias y contratos públicos. Catalog está materializado y validado localmente: 282 pruebas/7190 aserciones, Pint 249 archivos, PHPStan/OpenAPI correctos; migración aditiva de tres tablas vacías con las 30 anteriores preservadas, diez tablas Marketplace/Catalog vacías y ocho servicios saludables.

El plan completo aprobado incluye sus tres tablas vacías, código utilizado en cuatro capas, contrato API y pruebas. No se crean datos/grants positivos en desarrollo; positivos solo en bases temporales propias. Sin precios, inventario, SKU vendible, publicación por sucursal, activación, frontend o dependencias nuevas. DP-001/DP-002/DP-007/DP-012 y demás decisiones productivas permanecen abiertas. Commit/push/CI de 03A fueron autorizados después de presentar el resultado terminado.

[Evidencia y manifiesto del bloque](checkpoint-03a-evidence.md).

El usuario autorizó commit/push/CI mediante «Si autorizo» el 10 de octubre de 2026; véase el [registro de cierre](checkpoint-03a-evidence.md#publicación-confirmada). Publicado en main 0690cc0ec63493a163652ec68db52c184f700e26, [CI 38109718961 completa correcta](https://github.com/nromero96/traepe/actions/runs/38109718961), job 114382575818: SHA exacto y cuatro pasos obligatorios correctos.

## Checkpoint 03B — Productos adicionales y contenido actual

El usuario pidió continuar el proyecto. El [plan completo 03B](checkpoint-03b-plan.md) prepara alta de productos conceptuales adicionales, consulta del perfil actual, colección paginada y snapshot de alta adicional. Incluye capacidades independientes, versión esperada, idempotencia, cursor cifrado, integridad/diario y pruebas reales.

[DP-036](../../architecture/decisions/pending-decisions.md#dp-036--productos-adicionales-y-consulta-del-catálogo-actual) fue aprobada explícitamente mediante «Aprobar plan completo 03B (recomendado)» el 10 de octubre de 2026. Implementado y validado localmente: 307 pruebas/8532 aserciones, Pint 277, PHPStan 8 y contratos correctos; una tabla vacía/un índice aditivos, las 33 tablas anteriores preservadas, once tablas Marketplace/Catalog vacías y ocho servicios saludables. [Evidencia](checkpoint-03b-evidence.md). Mantiene los límites ficticios y decisiones productivas abiertas; commit/push/CI autorizados mediante «Autorizado» el 11 de octubre de 2026; [publicación y verificación remota en curso](checkpoint-03b-evidence.md#aprobación-y-publicación-autorizadas).
