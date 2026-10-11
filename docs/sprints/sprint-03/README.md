# Sprint 03 — Catálogo local del comercio

Fuente: maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79. El maestro distingue catálogo del comercio, producto conceptual, variante vendible y publicación por sucursal. Este sprint inicia el flujo de catálogo sobre la base comercial publicada en 02I.

## Base comprobada

02I publicado en main 0ab976d25c7e1c3993bc712ab437b5257ee4eed0, [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38098790155). Run 38098790155/job 114350184797 verificados sobre ese SHA: build/migración, calidad/integración, reinicio/persistencia y limpieza correctos. 255 pruebas locales y 5892 aserciones; siete tablas Marketplace vacías, ocho servicios saludables y datos anteriores preservados. [Evidencia de 02I](../sprint-02/checkpoint-02i-evidence.md).

## Checkpoint 03A — Alta y consulta de catálogo con primer producto

El usuario solicitó continuar con la recomendación de catálogo. El [plan 03A](checkpoint-03a-plan.md) autoriza un bloque funcional completo: POST crea catálogo y primer producto conceptual draft para un comercio ficticio creado por el mismo actor en 02I; GET consulta su snapshot original con permiso read independiente. Incluye persistencia, contrato público Marketplace, sesión/CSRF, idempotencia, auditoría cifrada y pruebas HTTP/DB reales.

DP-035 fue resuelta mediante «Aprobar plan completo 03A (recomendado)» el 10 de octubre de 2026. [ADR-008](../../architecture/decisions/ADR-008-catalog-local-draft-foundation.md) documenta las reglas aprobadas de datos/estados/borrado, propiedad, permisos, referencias y contratos públicos. Catalog está materializado y validado localmente: 282 pruebas/7190 aserciones, Pint 249 archivos, PHPStan/OpenAPI correctos; migración aditiva de tres tablas vacías con las 30 anteriores preservadas, diez tablas Marketplace/Catalog vacías y ocho servicios saludables.

El plan completo aprobado incluye sus tres tablas vacías, código utilizado en cuatro capas, contrato API y pruebas. No se crean datos/grants positivos en desarrollo; positivos solo en bases temporales propias. Sin precios, inventario, SKU vendible, publicación por sucursal, activación, frontend o dependencias nuevas. DP-001/DP-002/DP-007/DP-012 y demás decisiones productivas permanecen abiertas. Commit/push/CI de 03A se reservarán al resultado terminado.

[Evidencia y manifiesto del bloque](checkpoint-03a-evidence.md).

El usuario autorizó commit/push/CI mediante «Si autorizo» el 10 de octubre de 2026; véase el [registro de cierre](checkpoint-03a-evidence.md#aprobación-y-publicación-autorizadas). Publicación y validación remota en curso.
