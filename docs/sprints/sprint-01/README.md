# Sprint 01 — Identity backend local

Fecha: 10 de octubre de 2026. El usuario eligió la recomendación Identity backend local. Este documento prepara el checkpoint 01A. El usuario aprobó el cierre de 00F y los parámetros OTP el 10 de octubre de 2026; E.164 sin restricción de país, active/blocked y consentimiento ficticio local-v1 también aprobados.

## Fuente y alcance

Maestro v1.6 §§2.1, 35, 50, 59–60 y 71: identidad central separada de perfiles; celular/OTP para cliente; nombre y aceptación de términos; sesión revocable Sanctum cookie+CSRF; autorización por capacidad, alcance y recurso. §71 no define parámetros operativos del OTP.

01A propuesto: especificación y contrato del flujo cliente local; solicitud/verificación OTP mediante puerto con adaptador exclusivo local; identidad pública ULID y contacto normalizado único; consentimiento versionado; sesión cookie, consulta del usuario actual y cierre de sesión. Implementación dentro de Identity respetando Domain/Application/Infrastructure/Interfaces. Integración con la fundación HTTP, correlación y logs sanitizados existentes. La adaptación de users del starter requiere migración aditiva, preservando datos y restricciones.

Fuera de 01A: mercados, comercios, catálogo, stock, frontend, SMS real, proveedores externos, login de dashboards, MFA comercial, OAuth, dispositivos de confianza y permisos administrativos. No crear un rol comercial ni conceder capacidades implícitas.

## Decisiones y puertas

DP-001 permanece fuera de este checkpoint porque no implementa mercados. DP-010 continúa abierta para proveedores productivos; el adaptador local no la resuelve. Las demás decisiones abiertas conservan sus bloqueos registrados y no se aplican a este alcance acotado. Nilton revisará las 13 decisiones previas el 11 de octubre de 2026.

DP-025 está resuelta por aprobación explícita de todos los parámetros. 00F ya recibió aprobación explícita. No se instalarán dependencias ni se publicarán cambios sin la autorización correspondiente.

## Aceptación propuesta

- Especificación, OpenAPI y comportamiento coinciden; no se exponen IDs internos ni se revela si un celular existe.
- OTP expira, tiene intentos limitados, control de reenvío y es de un solo uso; solicitudes/verificaciones concurrentes no duplican identidad ni sesión mediante consumo doble.
- Contacto y estado se validan en servidor; política de normalización y estados aprobada antes de persistir.
- OTP, celular y cookies no aparecen en respuestas de diagnóstico ni logs. La entrega local tiene acceso restringido y nunca se habilita fuera de local/testing.
- Identidad y consentimiento se confirman atómicamente; se registra la versión explícita aprobada, sin aceptación implícita ni texto legal inventado.
- Sesión Sanctum con CSRF; logout revoca la sesión actual. Usuario bloqueado no obtiene sesión.
- Pruebas cubren éxito, formato inválido, expiración, reenvío, intentos, replay, concurrencia, bloqueo, CSRF, logout y privacidad. Quality e integración pasan.

## Secuencia

1. DP-025 y cierre de 00F aprobados.
2. Especificar contrato, migración compatible y casos de uso de 01A.
3. Implementar y validar el checkpoint; entregar diff, pruebas y riesgos para revisión.

Este documento es una propuesta ejecutable de alcance, no una nueva regla de negocio.
## Implementación preparada

Véanse [evidencia y pruebas de 01A](checkpoint-01a-evidence.md) y [operación local](local-runbook.md). Implementación aprobada por el usuario, publicada y validada localmente y en GitHub Actions para 748e937.

## Siguiente checkpoint

[01B: base de autorización](checkpoint-01b-plan.md) implementado conforme a DP-026 aprobada; [evidencia](checkpoint-01b-evidence.md). Validación local completa, pendiente aprobación/publicación del checkpoint. No incluye asignación de privilegios reales ni comercio/sucursales.
