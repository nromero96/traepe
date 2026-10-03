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

## Complemento aprobado — 3 de octubre de 2026

Para 00C, el usuario aprobó construir una imagen Docker local desde commits fijos del código oficial de MinIO y mc. El upstream oficial está archivado y distribuye código fuente; se documenta la limitación de mantenimiento. Esto conserva MinIO/S3 como adaptador local y no selecciona proveedor productivo. La imagen no se utilizará en producción. Las imágenes base, commits y política de bucket se versionan en `infrastructure/docker/minio/`; Laravel recibe una credencial limitada al bucket y no la credencial root de inicialización.
