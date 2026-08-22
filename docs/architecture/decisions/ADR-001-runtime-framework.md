# ADR-001 — Runtime y framework

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** Laravel 13 sobre PHP 8.4 para el backend maestro en `apps/api`.

## Contexto

El maestro v1.6 indica Laravel 12. Posteriormente se aprobó Laravel 13 con PHP 8.4 para iniciar la implementación técnica.

## Consecuencias

- Composer y CI deben validar PHP 8.4 y Laravel 13.
- Las dependencias se seleccionan solo si son compatibles con ambas versiones.
- Esta decisión sustituye exclusivamente la versión técnica Laravel 12 del maestro histórico.
- No modifica alcance, requisitos funcionales, estados, datos de negocio ni reglas comerciales del maestro v1.6.
