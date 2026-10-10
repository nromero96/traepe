# Operación de Identity local

Solo desarrollo. DP-025 aprobada: E.164 sin país fijo, OTP 6 dígitos/5 minutos/5 intentos/reenvío 60 segundos, estados active/blocked y consentimiento ficticio local-v1.

## Preparación

Seguir la instalación Docker de la fundación. Aplicar las migraciones aditivas:

```powershell
docker compose --env-file .env.docker exec -T api php artisan migrate --force --no-interaction
docker compose --env-file .env.docker exec -T api composer quality
docker compose --env-file .env.docker exec -T api composer test:integration
```

No ejecutar migrate:fresh, down -v ni rollback genérico. Identity conserva historia y su migración es forward-only.

## Prueba de sesión

Usar el mismo origen HTTP del backend (por defecto http://127.0.0.1:8000), con cookies habilitadas. Obtener GET /sanctum/csrf-cookie; enviar el valor URL-decodificado de XSRF-TOKEN en X-XSRF-TOKEN y las cookies en cada POST.

1. POST /api/v1/auth/otp/request con phone en E.164. Recibir data.id como challenge_id. No se devuelve el OTP.
2. El operador local entrega el código usando consola interactiva con acceso al contenedor:

```powershell
docker compose --env-file .env.docker exec api php artisan identity:local-otp CHALLENGE_ULID
```

No redirigir ni guardar esa salida en logs. El comando falla con --no-interaction, fuera de local o ante un desafío expirado/consumido/agotado.

3. POST /api/v1/auth/otp/verify con challenge_id, code (string de seis dígitos), name, consent_version igual a local-v1 y consent_accepted igual a true. Antes de aceptar, mostrar el aviso ficticio: «Prueba local de autenticación y consentimiento local-v1; no son términos productivos». No utilizarlo como contrato legal ni con usuarios productivos.
4. La respuesta pública identifica el usuario con ULID y status. La sesión y el token CSRF se regeneran: releer XSRF-TOKEN antes del siguiente POST.
5. GET /api/v1/auth/me consulta la identidad actual. POST /api/v1/auth/logout invalida la sesión; un GET posterior devuelve 401.

422 indica verificación fallida/entrada inválida, 419 CSRF faltante y 429 límite de solicitud/reenvío. Solicitud válida nunca revela si el contacto ya existe. No agregar permisos administrativos para probar el flujo.

El contrato exacto está en docs/api/openapi.yaml. Rutas y entrega OTP no están disponibles en producción; SMS/MFA/proveedores requieren checkpoint y decisiones separados.
## Prueba de autorización — 01D

GET /api/v1/identity/local-authorization-probe utiliza la misma sesión cookie de 01A. Sin sesión devuelve 401; con sesión y sin grant exacto devuelve 403. Ese rechazo es el comportamiento esperado del entorno de desarrollo con directorio vacío. No agregar permisos manualmente para obtener 200: el caso positivo se verifica en LocalAuthorizationProbeTest dentro de bases aisladas.

La ruta no acepta parámetros de actor, capacidad, scope, recurso o tiempo para modificar su decisión. El único recurso es un fixture técnico; no representa permisos administrativos. Solo local/testing; 404 fuera de esos entornos, incluso con ruta local cacheada. OpenAPI 1.2.0 documenta la sesión y respuestas. Los contadores del runtime conservan su límite de 30 requests/min; el aislamiento array se limita al harness de integración.

## Protección de entorno — 01E

Las cuatro rutas de autenticación de 01A también verifican local/testing en cada request, antes de web/auth/CSRF. Fuera de esos entornos devuelven 404, incluso con una sesión existente o una caché creada en local. El registro condicional de rutas y las restricciones del adaptador OTP siguen vigentes. OpenAPI 1.2.1 documenta esos rechazos.

LocalIdentityEnvironmentTest genera una caché en un directorio temporal propio y la verifica con procesos nuevos en production/staging. No ejecuta route:clear ni route:cache sobre la caché del runtime; elimina únicamente su archivo temporal. La suite de integración sigue utilizando bases PostgreSQL aisladas.
