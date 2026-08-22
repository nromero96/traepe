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
