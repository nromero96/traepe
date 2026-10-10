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

## Checkpoint 01C

[Directorio de autorización PostgreSQL](checkpoint-01c-evidence.md): adaptador de lectura y esquema vacío, con pruebas aisladas. No asigna privilegios ni agrega rutas. Implementación local preparada para revisión/publicación.

## Checkpoint 01D

[Integración HTTP local de autorización](checkpoint-01d-evidence.md): ruta de prueba conectada a sesión y directorio, con recurso ficticio fijado por servidor. Sin permisos asignados ni acciones comerciales. Validado localmente; pendiente aprobación/publicación.

## Estado confirmado al iniciar 01E

01B, 01C y 01D fueron aprobados y publicados. GitHub Actions pasó para `ae3888d`, `4fe405c` y `5f0f4f8`, respectivamente. [CI de 01D](https://github.com/nromero96/traepe/actions/runs/38045795191) también verificó persistencia tras reinicio y limpieza del entorno aislado. Los párrafos anteriores describen el estado al preparar cada checkpoint.

## Checkpoint 01E

[Protección de entorno de Identity local](checkpoint-01e-evidence.md): extiende el gate de 01D a las cuatro rutas de 01A y verifica una caché real local en procesos nuevos no locales. Mantiene el alcance de desarrollo aprobado, sin dependencias ni datos nuevos. Validado localmente: 75 pruebas/1622 aserciones y quality correcto. Aprobado el 10 de octubre de 2026; commit, push y validación remota autorizados.
