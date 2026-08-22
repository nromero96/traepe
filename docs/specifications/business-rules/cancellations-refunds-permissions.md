# Cancelaciones, reembolsos y permisos

**Origen:** secciones 23–25 del maestro v1.6.

## Cancelaciones

Usan una política versionada. La etapa y causa determinan quién asume productos, logística, pasarela, compensaciones y reembolso. Después de entregado no se cancela: se abre reclamo, ajuste o reembolso.

La causa siempre incluye código normalizado y, cuando corresponda, comentario/evidencia. Texto libre no puede ser la única causa. Los códigos iniciales se agrupan por cliente, tienda, repartidor, plataforma y fuerza mayor.

## Reembolsos y ajustes

- El reembolso devuelve dinero por el mismo medio cuando sea posible.
- Crédito promocional no reemplaza un reembolso obligatorio.
- Compensaciones registran autorización y financiador.
- Ajustes de tienda/repartidor modifican la liquidación mediante movimientos, no la historia original.
- El total reembolsado nunca supera lo capturado pendiente de devolver.

## Autorización

Las capacidades se evalúan con alcances `self`, `branch`, `merchant`, `market`, `platform` y `sensitive`. Operaciones sensibles o exportaciones requieren permiso reforzado y auditoría. El detalle completo por actor permanece en la matriz de la sección 25.
