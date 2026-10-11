# trae.pe

Marketplace peruano multitienda, multicategoría y logístico. Sprint 00 preparó el backend y su infraestructura; Identity, Marketplace y Catalog incorporan flujos ficticios locales con contratos y pruebas, sin operación comercial real.

## Estado actual

- Documento maestro vigente: versión 1.6, 22 de agosto de 2026.
- Arquitectura aprobada: monolito modular sobre Laravel 13 y PHP 8.4.
- Backend base instalado en `apps/api`: Laravel 13.34.0 sobre PHP 8.4.
- Interfaces futuras: cliente, comercio y administrador maestro como aplicaciones separadas.
- 00B publicado; 00C aprobado; 00D aprobado; 00E aprobado; 00F validado localmente y en GitHub Actions, revisado independientemente y aprobado el 10 de octubre de 2026; Sprint 00 cerrado. 00C–00F publicados con autorización explícita; GitHub Actions verde para `17784db`.
- Docker Compose ejecuta Nginx, PHP-FPM 8.4, PostgreSQL/PostGIS y Redis con imágenes fijadas.
- Horizon, Reverb, MinIO y Mailpit operativos en Docker; dashboard y canal técnico protegidos mediante credencial local aprobada. MinIO se construye desde commits oficiales fijos, solo para desarrollo. Sanctum 4.3.3 configura la base cookie/CSRF; GitHub Actions está configurado en 00F; su ejecución remota pasó tras la publicación autorizada. Véase [evidencia de 00F](docs/sprints/sprint-00/checkpoint-00f-evidence.md).
- Platform, Shared HTTP y generador modular probados; API técnica versionada con [OpenAPI](docs/api/openapi.yaml).
- Identity backend local (01A) implementado y validado, aprobado y publicado; CI verde para 748e937: OTP, consentimiento de prueba y sesión cookie. Sin frontend, SMS real ni dominios comerciales.

- Marketplace 02I publicado en 0ab976d25c7e1c3993bc712ab437b5257ee4eed0 con CI correcta. [Catalog 03A](docs/sprints/sprint-03/README.md) tiene plan completo aprobado: alta de catálogo/primer producto conceptual draft y consulta original propia; validación local completa, publicación autorizada mediante «Si autorizo»; commit/push/CI en curso. Sin SKU, precios, stock, frontend o datos reales.

## Navegación

- [Índice de documentación](docs/README.md)
- [Documento maestro](docs/master/Documento_Maestro_Funcional_trae_pe_v1_6.docx)
- [Especificaciones](docs/specifications/README.md)
- [Arquitectura](docs/architecture/README.md)
- [API](docs/api/README.md)
- [Eventos](docs/events/README.md)
- [Producto](docs/product/README.md)
- [Sprint 00](docs/sprints/sprint-00/README.md)
- [Instalación y calidad](docs/installation/README.md)
- [Instalación con Docker](docs/installation/docker.md)
- [Guía para agentes y contribuciones](AGENTS.md)

## Estructura objetivo

La estructura muestra el estado actual y los destinos futuros:

```text
apps/
  api/                  Backend maestro Laravel 13
    app/Modules/        Catalog, Identity, Marketplace y Platform materializados
    app/Shared/         Contratos HTTP transversales
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

Solo `apps/api` existe actualmente. Catalog, Identity, Marketplace, Platform, Shared y el generador tienen consumidores implementados. Los demás módulos únicamente al iniciar su implementación. No se crean directorios vacíos para simular avance.

## Fuente de verdad

El DOCX maestro v1.6 es la fuente oficial. Los Markdown facilitan navegación y desarrollo, pero no pueden ampliar ni contradecir sus reglas. Toda ambigüedad se registra como decisión pendiente.
