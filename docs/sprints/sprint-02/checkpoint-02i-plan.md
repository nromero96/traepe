# Plan aprobado de Checkpoint 02I — Alta y consulta de comercio con primera sucursal

Fecha: 10 de octubre de 2026. **Estado: aprobado explícitamente mediante «si apruebo»; implementado y validado localmente. Commit/push/CI autorizados al cierre mediante «si autorizo».** [Evidencia y manifiesto de cierre](checkpoint-02i-evidence.md).

Base main / origin/main `a95999d4a35e31f6344b8434cfb7b273915de946`, 02H publicado con [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38096356398). Se verificaron run/job y los cuatro pasos obligatorios sobre ese SHA. Desarrollo mantiene seis tablas Marketplace vacías y fundación correcta. Tras consultar el avance, el usuario aceptó continuar con un flujo funcional de comercio/sucursal en borrador, seguido posteriormente por catálogo.

## Resultado del bloque

Implementar una operación completa de backend: recibir datos de un comercio y su primera sucursal, crearlos atómicamente en draft y consultar el resultado autorizado. Incluye contrato API, permisos independientes de creación/lectura, idempotencia, auditoría y pruebas HTTP reales; deja una base usada para el siguiente bloque de catálogo.

Maestro v1.6 §§3.1, 9, 25, 32–36, 49–50, 55–60, 72 y 78–79; ADR-002, ADR-007 y DP-026/DP-032/DP-033. El usuario aprobó las políticas completas del plan mediante «si apruebo», resolviendo [DP-034](../../architecture/decisions/pending-decisions.md#dp-034--alta-y-consulta-local-de-comercio-con-primera-sucursal) antes de implementar, conforme a [AGENTS.md](../../../AGENTS.md). La publicación continúa reservada al cierre.

Alcance exclusivamente local/testing con datos ficticios. No registro de empresas/personas reales, activación, fiscalidad/riesgo favorables, dashboard administrativo o excepción a MFA sensible. No frontend/dependencias nuevos ni resolución de DP-001/DP-012. La entrega es un flujo de API verificable de principio a fin.

## Entrada y contrato

POST `/api/v1/marketplace/local-draft-commerces`, JSON cerrado con exactamente merchant y branch; orden de propiedades indiferente. Cada objeto exige únicamente los campos indicados:

```json
{
  "merchant": {
    "legal_name": "Synthetic Merchant",
    "trade_name": "Synthetic Commerce"
  },
  "branch": {
    "market_public_id": "01ARZ3NDEKTSV4RRFFQ69G5FAZ",
    "name": "Synthetic Branch",
    "longitude": 0.5,
    "latitude": 1.5,
    "timezone": "Etc/UTC"
  }
}
```

El ULID del ejemplo es sintético y debe existir como mercado draft para un caso positivo. No se crea país/mercado/zona desde este endpoint. Las pruebas preparan el mercado mediante fixtures propios o 02F, exclusivamente en bases temporales aisladas.

Nombres: strings JSON UTF-8 de 1–255 caracteres, no vacíos tras btrim de espacios ASCII y sin controles U+0000–001F/U+007F. Se conservan exactamente, sin trim/cambio de mayúsculas; no unicidad de nombres. Market ULID estricto mayúsculo. Coordenadas: números JSON (sin strings/bools), finitos y en rangos WGS84, validados mediante GeographicPoint antes del cast a geography; sin redondeo programado, validación sobre el número decodificado por PHP. Timezone requerida exactamente Etc/UTC en este ejercicio ficticio, sin herencia/default.

El cliente no proporciona public_id internos/públicos nuevos, estado, versión, tax_id, risk_level, usuario, merchant_id ni configuración comercial. Servidor genera ULID, impone status=draft/version=1, tax_id/risk_level=NULL, merchant raíz sin market_id y branch ligada al merchant nuevo y mercado draft solicitado. Validación de formato anterior a SQL de negocio; autorización anterior a entrada sensible.

Respuesta 201: recurso local_draft_commerce_operation con operation ULID y snapshot v1 del merchant y branch creados, public_id, datos de entrada, draft/version=1 y market_public_id. Sin bigint, datos de Identity, hashes técnicos ni evaluación fiscal/riesgo. GET `/api/v1/marketplace/local-draft-commerces/{operationPublicId}` devuelve 200 con ese snapshot original, solo al actor creador con permiso read vigente; otros actores y operación inexistente dan 404 indistinguible tras verificar acceso.

Ambas rutas mantienen correlation_id actual y Cache-Control no-store/private, sin caché compartida. OpenAPI pasa a 1.6.0 y agrega un snapshot cerrado de alta comercial v1 sin modificar el snapshot fixture v1 anterior. Errores estables/sanitizados: 401 sesión, 403 permiso, 419 CSRF, 415 media, 422 formato, 404 lectura no disponible, 409 mercado no disponible/idempotencia mismatch/expired, 429 tasa y 500 genérico. Sin nombres, coordenadas, cuerpo, claves o SQL en errores/logs.

## Acceso aprobado

Sesión cookie de Identity y CSRF en POST, conservando rotación/revocación y DP-026: capacidad/alcance/recurso exactos, deny por defecto y verificación del actor activo. Capacidades independientes `marketplace.local.draft_commerce.create` y `marketplace.local.draft_commerce.read`.

Scope técnico platform `01ARZ3NDEKTSV4RRFFQ69G5FB2`; recurso create de tipo igual a su capacidad y ULID `01ARZ3NDEKTSV4RRFFQ69G5FB5`; read equivalente con `01ARZ3NDEKTSV4RRFFQ69G5FB6`. Referencias resueltas por servidor mediante adaptadores propios que usan contratos públicos de Identity. No resolver global sustituido, wildcard, grant automático, acceso público, privilegio administrativo ni capacidad read implícita desde create. Leer requiere además actor_public_id creador exacto en el historial privado Marketplace.

Registro de rutas/bindings solo local/testing y guard antes de sesión, autenticación, DI y SQL, incluso con cachés locales reutilizadas en production/staging. APP_ENV no concede acceso. Limitadores independientes create/read de 30/minuto por actor verificado, sin compartir presupuesto con OTP/02F; no registrar cuerpo, cookie o clave. Operación local técnica como 02F, sin habilitar administración sensible; cualquier dashboard/acción administrativa real seguirá requiriendo MFA conforme a §60.

## Transacción, concurrencia e idempotencia

Idempotency-Key ASCII imprimible !–~ de 1–255 bytes sin espacios/control, sin modificación silenciosa. Scope `marketplace.local_draft_commerce.create.v1:<FB2>:<FB5>`, actor `identity.user:<ULID verificado>`, fingerprint canónico de schema_version=1 y entrada validada. Los números geográficos se representan como strings canónicas de los float validados en el payload de fingerprint: entero y decimal equivalentes coinciden, cero negativo coincide con cero; no se cambia RequestFingerprint de Platform, que rechaza floats. El resto de campos conserva su valor exacto.

Vigencia 24 horas desde primer claim UTC, sin extensión, purga/reuso automático ni política productiva de retención. Puerto público Platform reserva/bloquea la clave y ejecuta un callback compartiendo transacción/conexión PostgreSQL. Callback revalida el mismo actor/capacidad y bloquea el mercado por ULID, comprobando draft antes de escribir. Crea merchant, branch y auditoría; cualquier fallo revierte todo, incluido claim. Competencia de una misma clave crea un solo conjunto; claves distintas pueden crear comercios/sucursales con nombres iguales, sin unicidades nuevas.

Replay exige create vigente, no crea/actualiza filas y devuelve snapshot original 201 sin extender TTL. Resolver el resultado de POST exige propiedad del actor; no exige read para su propia respuesta de creación. GET exige read y propiedad, sin leer/modificar el claim. Si cambian permisos/bloqueo/sesión, se deniega también replay; no se promete serialización con una futura administración de grants. Sin evento público ni efecto externo en este ejercicio, no se añade outbox/consumidor sin uso.

## Auditoría y migración

Nueva tabla privada `marketplace_local_commerce_operations` por migración aditiva/transaccional/forward-only. Campos: bigint id interno, public_id ULID unique, merchant_id y branch_id FK restrict unique a filas propias creadas, market_id FK restrict/index, actor_public_id ULID/index sin FK a Identity, schema_version=1, key_hash/request_hash/response_hash SHA-256, correlation_id ULID, response_snapshot_ciphertext text requerido y created_at UTC/index. Sin updated_at/deleted_at. Checks de identificadores/hashes/version y ciphertext no vacío; trigger rechaza UPDATE/DELETE.

Snapshot completo cifrado con Crypt de Laravel y clave ignorada; hash de integridad comprobado al restaurar y forma cerrada validada antes de devolverlo. Platform conserva solo fingerprint y referencia pública, nunca nombres, ubicación, petición o respuesta. Diario append-only registra causa/actor/correlación/referencias; no copia datos privados de Identity. Los campos base merchants/branches siguen como en ADR-007 para datos ficticios; este ejercicio no aprueba tratamiento de datos personales reales ni retención productiva.

Primero pruebas aisladas; después aplicar localmente únicamente la nueva tabla vacía. Comparar todas las tablas/conteos previos: ninguna fila cambia, solo una tabla vacía y un registro de migración. Segunda migrate sin cambios. Desarrollo conserva siete tablas Marketplace vacías y ningún usuario/rol/grant nuevo; casos positivos solo en bases propias eliminadas al terminar. Sin reset, migrate:fresh/rollback local, eliminación de volúmenes o datos ajenos.

## Composición y criterios de aceptación

Domain: entrada/resultado inmutables, validaciones y fallos tipados, reutilizando IDs y GeographicPoint existentes. Application: casos de alta/lectura y puertos de acceso, escritura idempotente e historial. Infrastructure: adaptadores Identity/Platform por contratos públicos y almacenamiento PostgreSQL/cifrado de Marketplace. Interfaces: controladores delgados y validación de forma; permisos/rutas limitados por entorno. Shared solo utiliza respuestas/observabilidad ya existentes. No modelos/carpetas/contratos para otros módulos sin consumidor.

Pruebas proporcionales: validación/nombres/punto/timezone; autenticación, CSRF, capacidades independientes, actor y aislamiento; contrato/snapshot/cifrado/integridad, sin filtración de PII en errores/logs; alta/consulta HTTP real con OTP cookie, reintento idéntico, mismatch/expired, bloqueo/logout/deny, actor distinto/404 y tasa independiente. PostgreSQL real: FK/constraints/append-only, dos procesos concurrentes, fallo de branch/historial/reautorización con rollback total y replay inmutable; preservación de base anterior/aplicación vacía/segunda migrate. Regresiones 02A–02H, Identity/Platform, límites modulares, Pint/PHPStan/OpenAPI/Unit/Feature/Integration, fundación/runtime/puertas negativas/secretos/enlaces/hash del maestro. Evidencia y manifiesto exacto antes de solicitar publicación.

Archivos previstos: componentes utilizados de Commerce y sus adaptadores/controladores, MarketplaceServiceProvider, migración 000006, pruebas Unit/Feature/Integration/HTTP real y verificador de fundación; OpenAPI/snapshot/lint y documentación ADR/especificación/arquitectura/datos/seguridad/evidencia. Sin dependencias, frontend, catálogo/stock, activación, membresías, contratos/comisiones, horarios o geocodificación nuevos en este bloque.

La aprobación inicial de DP-034 autorizó implementar/validar este bloque completo y aplicar localmente la migración vacía, reservando commit/push/CI al cierre revisable. Después de presentar la evidencia terminada, el usuario autorizó commit/push/CI mediante «si autorizo» el 10 de octubre de 2026; véase el [registro](checkpoint-02i-evidence.md#aprobación-y-publicación-autorizadas). DP-001 y las demás decisiones productivas conservan sus responsables/fechas y siguen abiertas.
