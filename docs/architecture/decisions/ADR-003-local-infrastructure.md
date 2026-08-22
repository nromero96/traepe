# ADR-003 — Infraestructura local

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** Docker Compose, PostgreSQL/PostGIS, Redis, MinIO y Mailpit para desarrollo local.

## Alcance

- MinIO implementa localmente el contrato S3.
- Mailpit captura correo local.
- Redis soportará caché, colas y componentes reconstruibles; no es fuente transaccional.
- Proveedores productivos, CD y hosting continúan pendientes.

## Consecuencias

El stack debe fijar versiones, usar health checks, evitar secretos productivos y ser reproducible. Su ejecución comienza en Checkpoint 00B, no en 00A.
