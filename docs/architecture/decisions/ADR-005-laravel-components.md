# ADR-005 — Componentes oficiales de Laravel

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** Laravel Sanctum, Horizon y Reverb.

## Alcance

- Sanctum será la base técnica de autenticación; Checkpoint 00A no implementa autenticación funcional.
- Horizon operará colas Redis desde Checkpoint 00C.
- Reverb proporcionará WebSockets desde Checkpoint 00C.
- La cola utiliza entrega al menos una vez. Los jobs y consumidores deben ser idempotentes para que los reintentos no dupliquen efectos.

## Consecuencias

Dashboards técnicos y canales privados requieren autorización. Ningún componente introduce reglas comerciales durante Sprint 00.

## Autorización técnica local aprobada — 3 de octubre de 2026

El usuario aprobó una credencial técnica exclusiva de desarrollo en `.env.docker` ignorado, sin usuarios ni adelantar Sanctum. Horizon usa HTTP Basic; Reverb autoriza exclusivamente `private-technical.v1` mediante el mismo acceso técnico. Se deniega fuera de local, con secreto vacío/corto, credencial incorrecta o canal ajeno. Estar en local nunca basta para autorizar. Esto no define identidad ni permisos de negocio.

## Fundación Sanctum de 00D — 3 de octubre de 2026

Tras aprobar 00C, el usuario autorizó explícitamente 00D. Sanctum 4.3.3 queda instalado con configuración de hosts stateful locales, guard web y middleware cookie/CSRF en el grupo API. Se conserva la ruta nativa GET `/sanctum/csrf-cookie`. Pruebas verifican cookies, denegación sin autenticación, orígenes ajenos y rechazo/aceptación CSRF real sin el bypass habitual del entorno testing. No se publican flujos de identidad, tokens ni permisos de negocio, ni se modifica la credencial técnica de 00C.
