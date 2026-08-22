# AGENTS.md

## Alcance

Lee primero `docs/README.md` y el documento maestro v1.6 ubicado en `docs/master/`.

Los archivos Markdown son una representación técnica navegable. El documento maestro conserva precedencia sobre cualquier resumen derivado.

Si existe una contradicción, omisión o ambigüedad:

1. No inventes una regla.
2. No elijas silenciosamente una interpretación.
3. Regístrala en `docs/architecture/decisions/pending-decisions.md`.
4. Detén la implementación afectada si la decisión modifica datos, dinero, seguridad o comportamiento del negocio.

## Arquitectura

- Backend maestro: Laravel 13 con PHP 8.4 en `apps/api`.
- Arquitectura inicial: monolito modular.
- Los módulos se ubican en `apps/api/app/Modules`.
- Módulos iniciales: Identity, Marketplace, Catalog, Pricing, Ordering, Payments, Logistics, Settlements, Support, Engagement, Platform y DataAI.
- `apps/api/app/Shared` solo admite componentes estables y verdaderamente transversales; no reglas pertenecientes a un dominio vertical.
- Las capas de cada módulo son: Domain, Application, Infrastructure e Interfaces.

Dirección permitida de dependencias:

- Domain no depende de Laravel, HTTP, Eloquent, Redis, colas ni proveedores externos.
- Application depende de Domain y define casos de uso y puertos.
- Infrastructure implementa los puertos definidos por Application o Domain.
- Interfaces recibe solicitudes, valida formato, invoca casos de uso y transforma respuestas.
- Los controladores deben ser delgados y no contener reglas de negocio.
- Un módulo no consulta directamente las tablas privadas de otro módulo.
- La comunicación entre módulos utiliza contratos, casos de uso, eventos o proyecciones públicas.
- PostgreSQL es la fuente de verdad transaccional.
- Redis, cachés, índices de búsqueda y proyecciones son reconstruibles y no constituyen la fuente de verdad.

## Convenciones

- API versionada bajo `/api/v1`.
- IDs internos `bigint`.
- IDs públicos ULID únicos e indexados.
- No exponer IDs internos secuenciales.
- Dinero almacenado como unidades mínimas enteras más código de moneda; nunca utilizar `float`.
- Fechas almacenadas en UTC.
- La zona horaria del mercado, tienda o usuario se conserva separadamente.
- Toda información operativa debe respetar el alcance de mercado, organización, tienda o sucursal correspondiente.
- Mutaciones críticas requieren idempotencia, control de concurrencia, historial, `correlation_id`, auditoría y outbox cuando corresponda.
- La autorización considera capacidad, alcance y recurso.
- Finanzas y auditoría son append-only.
- Las correcciones financieras se realizan mediante movimientos compensatorios.
- El contenido de un evento publicado es inmutable.
- Los metadatos técnicos de entrega del outbox pueden actualizarse sin modificar el contenido original del evento.
- Los eventos, webhooks y contratos públicos deben estar versionados.

## Pruebas

- Unit: objetos de valor, políticas, estados, cálculos y reglas de dominio.
- Feature: API, validación, autenticación, autorización, idempotencia y concurrencia.
- Contract: integraciones, webhooks, esquemas y eventos versionados.
- Integration: PostgreSQL/PostGIS, Redis, colas y almacenamiento reales en CI cuando estén disponibles.
- Las pruebas deben incluir casos exitosos, rechazos esperados y límites relevantes.
- Una prueba no debe depender del orden de ejecución de otras pruebas.

## Seguridad y privacidad

- No incluir PII, credenciales, tokens, datos de pago ni secretos en logs.
- No guardar secretos dentro del repositorio.
- Validar entrada en los límites del sistema.
- Aplicar mínimo privilegio.
- Registrar operaciones administrativas y críticas.
- No confiar en precios, descuentos, totales, permisos o estados enviados por el cliente.
- Toda operación sensible debe verificar nuevamente su autorización en el servidor.

## Restricciones

- No acoplar el núcleo a Tienda Siete, licores o una sola categoría comercial.
- No instalar dependencias ni generar aplicaciones sin aprobación explícita.
- No cambiar una regla de negocio únicamente en código; actualizar también su especificación o ADR.
- No crear carpetas vacías para representar arquitectura futura.
- No introducir microservicios sin una necesidad documentada y aprobada.
- No realizar `commit`, `push`, merge, publicación o despliegue sin aprobación explícita.
- No modificar archivos ajenos al alcance de la tarea.
- No eliminar cambios existentes del usuario.

## Definición de terminado

Un cambio está terminado cuando:

- Cumple sus criterios de aceptación.
- Mantiene trazabilidad con la especificación correspondiente.
- Incluye pruebas proporcionales al riesgo.
- Respeta los límites modulares y la dirección de dependencias.
- Actualiza documentación, contrato o ADR cuando corresponda.
- Supera formato, análisis estático y pruebas disponibles.
- No introduce secretos, PII en logs ni dependencias innecesarias.
- Las migraciones incluyen restricciones de integridad e índices necesarios.
- Las migraciones permiten despliegues compatibles hacia adelante.
- El resultado final incluye un resumen de archivos modificados, validaciones ejecutadas y riesgos pendientes.
