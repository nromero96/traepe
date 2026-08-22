# Seguridad y privacidad

**Origen:** secciones 2.1, 12, 25, 49, 60 y 64 del maestro v1.6.

## Identidad y acceso

- PWA web: Sanctum cookie + CSRF en mismo dominio.
- Dashboards: Sanctum + MFA para acciones sensibles.
- Apps futuras: OAuth 2.1/OIDC con PKCE o tokens de dispositivo.
- Integraciones: client credentials o HMAC por proveedor/alcance.
- Webhooks: firma, timestamp, ventana anti-replay, inbox y deduplicación.
- WebSockets: token corto y autorización de canal privado.

La autorización combina capacidad, alcance y recurso. Las policies delegan en un servicio de permisos; no basta con un rol global. Las acciones sensibles exigen reautenticación/MFA y auditoría.

## Controles mínimos

Mínimo privilegio, cifrado, rate limiting, CSP, CSRF, CORS cerrado, rotación de secretos, escaneo de dependencias y retención/finalidad de datos. Logs sin secretos ni PII innecesaria. Acceso/exportación sensible es controlado y auditado.

## Datos

No exponer IDs internos. Eliminar o anonimizar según política; nunca borrar historia financiera, eventos o auditoría que deban conservarse. Las decisiones concretas de retención y proveedores se mantienen pendientes hasta aprobación.
