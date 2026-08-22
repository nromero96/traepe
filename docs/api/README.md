# Contrato API

**Origen:** secciones 58–63 del maestro v1.6.

## Convenciones obligatorias

- Prefijo `/api/v1`, JSON UTF-8 y fechas ISO 8601 UTC.
- Importes como enteros más moneda.
- Recursos por ID público; nunca bigint secuencial.
- Paginación cursor-based con `next_cursor` y `has_more`.
- Errores con `code` estable, mensaje humano, detalles y `correlation_id`.
- POST críticos con `Idempotency-Key`; concurrencia con `If-Match` o versión.

## Forma de respuesta

- Singular: `data` y `meta.correlation_id`.
- Colección: `data[]` y metadatos de cursor/filtros.
- 422: `validation_failed` y detalles por campo.
- 409: `invalid_transition`, `version_conflict` o `idempotency_mismatch`.
- 429: error y `Retry-After`.
- 202: `operation_id` y URL de estado.

## Superficie mínima prevista

Autenticación OTP; resolución de mercado; tiendas/catálogos; carrito/cotización; creación/consulta/cancelación de pedidos; aceptación/rechazo/preparación de tienda; ofertas/hitos de entrega; intervención administrativa.

Las rutas exactas del maestro se conservan como propuesta inicial, no como implementación. Antes de codificar se deberá producir un contrato versionado sin ampliar reglas de negocio.

## Webhooks

Validar tamaño, cuerpo crudo, timestamp, firma y cuenta; persistir inbox; responder 2xx a duplicados; procesar por job idempotente y enviar a dead-letter según política de reintentos.

## Integraciones lógicas

`PaymentGateway`, `RoutingProvider`, `NotificationProvider`, `StorageProvider`, `IdentityProvider` e `InvoicingProvider`. Los proveedores concretos son decisiones pendientes.
