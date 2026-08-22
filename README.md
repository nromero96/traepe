# trae.pe

Marketplace peruano multitienda, multicategoría y logístico. Checkpoint 00A del Sprint 00 instaló la base del backend; todavía no existen funcionalidades comerciales.

## Estado actual

- Documento maestro vigente: versión 1.6, 22 de agosto de 2026.
- Arquitectura aprobada: monolito modular sobre Laravel 13 y PHP 8.4.
- Backend base instalado en `apps/api`: Laravel 13.26.1 sobre PHP 8.4.
- Interfaces futuras: cliente, comercio y administrador maestro como aplicaciones separadas.
- S00-001, S00-002 y S00-003 completadas; Checkpoint 00B requiere nueva aprobación.
- No se ejecutaron Docker, PostgreSQL, Redis, Horizon, Reverb, MinIO, Mailpit ni GitHub Actions.
- No se implementaron autenticación funcional ni dominios comerciales.

## Navegación

- [Índice de documentación](docs/README.md)
- [Documento maestro](docs/master/Documento_Maestro_Funcional_trae_pe_v1_6.docx)
- [Especificaciones](docs/specifications/README.md)
- [Arquitectura](docs/architecture/README.md)
- [API](docs/api/README.md)
- [Eventos](docs/events/README.md)
- [Producto](docs/product/README.md)
- [Sprint 00](docs/sprints/sprint-00/README.md)
- [Guía para agentes y contribuciones](AGENTS.md)

## Estructura objetivo

La estructura muestra el estado actual y los destinos futuros:

```text
apps/
  api/                  Backend maestro Laravel 13
    app/Modules/        Platform se materializará en 00D
    app/Shared/         Se materializará en 00D
  customer-web/         Web/PWA cliente
  merchant-dashboard/   Interfaz de comercio y sucursal
  admin-dashboard/      Interfaz exclusiva del administrador maestro
packages/
  design-tokens/
  api-contracts/
  tooling/
infrastructure/
docs/
```

Solo `apps/api` existe actualmente. Platform, Shared y el generador se crearán en 00D; los demás módulos únicamente al iniciar su implementación. No se crean directorios vacíos para simular avance.

## Fuente de verdad

El DOCX maestro v1.6 es la fuente oficial. Los Markdown facilitan navegación y desarrollo, pero no pueden ampliar ni contradecir sus reglas. Toda ambigüedad se registra como decisión pendiente.
