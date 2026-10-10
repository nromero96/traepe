# Checkpoint 01A — Identity backend local

Fecha: 10 de octubre de 2026. Implementado bajo maestro v1.6 §§2.1, 35, 50, 59–60 y 71, aprobación de alcance Identity local y DP-025 resuelta. Pendiente revisión/aprobación del checkpoint y autorización de publicación. No se instalaron paquetes ni modificaron lockfiles.

## Resultado

- Identity materializado con Domain (E.164, política OTP y consentimiento explícito), Application (puerto local), Infrastructure (PostgreSQL, usuario autenticable y provider), Interfaces (HTTP y entrega interactiva local).
- POST /api/v1/auth/otp/request: teléfono canónico E.164; desafío ULID; 6 dígitos, TTL 300 segundos, reenvío tras 60 segundos invalidando el desafío anterior. Respuesta nunca contiene código ni contacto.
- POST /api/v1/auth/otp/verify: desafío/código, nombre y aceptación explícita de local-v1. Máximo 5 intentos, consumo único, respuesta neutra ante rechazo. Contacto y nombre cifrados; unicidad de contacto mediante HMAC con APP_KEY. No se valida existencia real del número ni país.
- Identidad active/blocked, perfil separado, consentimiento único versionado y auditoría correlacionada. Confirmación y consumo del desafío en una transacción. PostgreSQL rechaza UPDATE/DELETE sobre consentimiento y auditoría.
- GET /api/v1/auth/me y POST /api/v1/auth/logout: sesión Sanctum cookie, CSRF en mutaciones, regeneración al autenticar e invalidación al salir; consulta actual revalida active. Logout registra auditoría; aun si falla ese registro, invalida la sesión mediante finally.
- Entrega de desarrollo mediante identity:local-otp: requiere entorno local e interacción de consola con acceso al contenedor. El código se muestra exclusivamente al operador, nunca en logs ni respuestas API. Proveedor y rutas de Identity deshabilitados fuera de local/testing; el adaptador además rechaza solicitudes OTP en producción.

## Validación

- Pint: 113 archivos correctos. PHPStan nivel 8: sin errores. OpenAPI 3.0: válido y rutas consistentes.
- Unit/Feature: 34 pruebas, 1091 aserciones. Suite PostgreSQL completa: 13 pruebas, 114 aserciones; incluye 6 de Identity. Total: 47 pruebas, 1205 aserciones.
- Dos procesos independientes verifican el mismo desafío: uno acepta y otro rechaza; una identidad, un consentimiento y un registro de verificación.
- Fallo de escritura de auditoría revierte identidad/perfil/consentimiento y consumo/intentos OTP. Reintento posterior correcto.
- Expiración, reenvío, límites, replay, bloqueo, consentimiento obligatorio, CSRF, logout e inmutabilidad cubiertos.
- Fallas controladas de Pest/Pint/PHPStan: exit 1 esperado. verify-foundation: salud/readiness, correlación, Horizon, privacidad de logs, módulos aprobados y ausencia de tablas de pedidos/pagos/catálogo correctos.
- Migración aplicada al entorno local sin borrar datos ni volúmenes. Pruebas de integración utilizan/eliminan únicamente sus bases aleatorias propias.

## Archivos y compatibilidad

Nuevos: app/Modules/Identity, migración 2026_10_10_000001, pruebas Unit/Feature/Integration y worker de carrera; especificación/runbook/evidencia de Sprint 01. Modificados: bootstrap/providers, config/auth, OpenAPI y pruebas de arquitectura/contrato/fundación. El generador usa Support como fixture aislado para no redeclarar el provider real de Identity. Rollback del test Platform apunta explícitamente a su propia migración; no rebobina Identity.

La migración permite email/password nulos para OTP, agrega ULID/estado/contacto sin destruir campos anteriores y asigna ULID a usuarios existentes. Es forward-only para preservar identidad/consentimientos/auditoría; revertir exige plan revisado, no migrate:rollback genérico. El cambio de proveedor usa IdentityUser; el starter App/Models/User se conserva para compatibilidad histórica/factories.

También permanecen cambios documentales del cierre aprobado de Sprint 00 y su revisión independiente. No se descartan esos cambios.

## Límites

Sin SMS real, frontend, roles/capacidades comerciales, mercados ni MFA. local-v1 es un consentimiento ficticio para prueba de desarrollo; nunca términos productivos. APP_KEY protege tanto cifrado como HMAC; rotarla exige un plan de recifrado/reindexación. Retención y proveedores productivos siguen fuera de este checkpoint. CI remoto en verde corresponde a f009a1b/Sprint 00; estos cambios no se han publicado ni ejecutado en GitHub Actions.
HTTP real vía Nginx: GET /api/v1/auth/me anónimo devuelve 401; POST /api/v1/auth/otp/request sin CSRF devuelve 419. Los ocho servicios permanecen healthy. El flujo completo de sesión se verificó mediante kernel HTTP en la base aislada de integración, no creando usuarios ficticios en la base de desarrollo.

## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «apruebo y autorizo» a la aprobación de 01A, commit/push y validación en GitHub Actions. Se publicarán el checkpoint y la documentación acumulada del cierre de Sprint 00. El resultado remoto se comprobará para el SHA publicado antes de declarar éxito.
