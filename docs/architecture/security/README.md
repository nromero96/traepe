# Seguridad y privacidad

**Origen:** secciones 2.1, 12, 25, 49, 60 y 64 del maestro v1.6.

## Identidad y acceso

- PWA web: Sanctum cookie + CSRF en mismo dominio.
- Dashboards: Sanctum + MFA para acciones sensibles.
- Apps futuras: OAuth 2.1/OIDC con PKCE o tokens de dispositivo.
- Integraciones: client credentials o HMAC por proveedor/alcance.
- Webhooks: firma, timestamp, ventana anti-replay, inbox y deduplicación.
- WebSockets: token corto y autorización de canal privado.

La autorización combina capacidad, alcance y recurso. Las policies delegan en un servicio de permisos; no basta con un rol global. Las acciones sensibles exigen reautenticación/MFA y auditoría.

## Controles mínimos

Mínimo privilegio, cifrado, rate limiting, CSP, CSRF, CORS cerrado, rotación de secretos, escaneo de dependencias y retención/finalidad de datos. Logs sin secretos ni PII innecesaria. Acceso/exportación sensible es controlado y auditado.

## Datos

No exponer IDs internos. Eliminar o anonimizar según política; nunca borrar historia financiera, eventos o auditoría que deban conservarse. Las decisiones concretas de retención y proveedores se mantienen pendientes hasta aprobación.


## Observabilidad técnica 00E

Los logs usan una lista cerrada de campos y mensajes técnicos; los datos del request y las excepciones completas se descartan. El evento ficticio del outbox se cifra con APP_KEY y su contenido e identidad son inmutables mediante trigger PostgreSQL. Redis recibe una referencia pública, sin payload. La correlación es trazabilidad, nunca autorización. Véase la [operación local](../../installation/docker.md#checkpoint-00e-correlación-y-entrega-técnica).

## Núcleo de autorización — 01B

DP-026 aprobada el 10 de octubre de 2026: denegar por defecto y para actor bloqueado; denegación explícita vigente prevalece; coincidencia exacta de alcance sin herencia ni comodines; expiración al alcanzar el instante UTC; contexto del recurso resuelto por servidor y coherente con la solicitud. El núcleo Domain/Application y sus contratos se prueban con fixtures, sin conceder privilegios ni agregar endpoints. Véanse [plan](../../sprints/sprint-01/checkpoint-01b-plan.md) y [evidencia](../../sprints/sprint-01/checkpoint-01b-evidence.md). Su integración persistente y las políticas comerciales quedan para checkpoints posteriores.

## Lectura técnica de borradores — 02E

DP-030 aprobada mediante «aprobar y continuar» el 10 de octubre de 2026. La nueva sonda HTTP persistida exige sesión cookie del flujo local de Identity y capacidad exacta `marketplace.local.persisted_coverage.read`, scope platform `01ARZ3NDEKTSV4RRFFQ69G5FB2` y recurso técnico `marketplace.local.persisted_coverage` / `01ARZ3NDEKTSV4RRFFQ69G5FB3`, resueltos por el servidor. La capacidad autoriza el diagnóstico técnico de cualquier mercado draft solicitado, con aislamiento de cada consulta; no concede permisos operativos por mercado. Conocer un ULID, estar autenticado o disponer de la credencial de Horizon no basta.

El middleware web y auth:web usa únicamente la cookie de sesión existente; no acepta autenticación por bearer/basic ni crea tokens. DP-026 se reevalúa por solicitud mediante los contratos de Identity. Sin sesión: 401; permiso ausente, actor bloqueado, deny vigente o expiración: 403 antes de validación y de cualquier consulta geográfica. Inputs de actor, scope, recurso, capacidad o reloj no determinan la autorización. Límite separado de 30/minuto por actor; no-store, correlación y errores sanitizados.

Fuera de local/testing, ruta/bindings ausentes; un gate de ejecución devuelve 404 incluso con rutas cacheadas en local, antes de iniciar sesión o consultar los módulos. El adaptador también deniega antes del directorio Identity. No se concede acceso positivo en desarrollo: usuarios/permisos y borradores de las pruebas viven solo en bases propias temporales. La lectura conserva los datos de negocio; sesión/limitador pueden actualizar metadatos técnicos. No agrega acciones administrativas, datos operativos ni MFA productivo. [Plan](../../sprints/sprint-02/checkpoint-02e-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02e-evidence.md).

## Escritura técnica de fixtures — 02F

DP-031 aprobada exige POST con sesión cookie y CSRF válidos, capacidad marketplace.local.draft_fixture.create, scope platform 01ARZ3NDEKTSV4RRFFQ69G5FB2 y recurso marketplace.local.draft_fixture.create / 01ARZ3NDEKTSV4RRFFQ69G5FB4, todos resueltos por servidor. Read no concede create ni create concede read. DP-026 completa, incluida denegación de actor bloqueado, expiración al alcanzar el instante y prioridad de deny. Actor público obtenido del contrato Identity; entradas/cabeceras de autoría, permisos, scope y reloj no lo sustituyen.

El gate fuera de local/testing precede sesión/CSRF/DI incluso con caché real; bindings positivos ausentes y adaptadores vuelven a denegar antes de consultas. Autorización precede validación y claim; se revalida dentro del callback y antes de devolver la operación, también en replay. JSON crudo de forma cerrada evita normalización del perfil; Idempotency-Key admite únicamente ASCII imprimible sin espacios/control, longitud 1–255. Hashes, snapshot sin PII, correlación, no-store/private y rate limit propio 30/minuto por actor. Errores no reflejan campos, valores, claves ni SQL. Pruebas reales verifican CSRF, OTP, cookie, revocación, bloqueo, logout y cookie replay. No hay grants nuevos en desarrollo ni privilegios administrativos productivos. [Plan](../../sprints/sprint-02/checkpoint-02f-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02f-evidence.md).

## Base comercial sin acceso nuevo — 02G

DP-032 aprueba únicamente dos tablas privadas vacías, solo draft y tax_id/risk_level exclusivamente NULL. No agrega autenticación, capacidades, grants, membresías, API ni onboarding. Conocer public_id o existir como Merchant/Branch no concede permisos; los scopes/recursos actuales de Identity mantienen DP-026 y su resolución por servidor.

FK e índices de merchant_id/market_id preparan el alcance de los datos del dueño Marketplace. Ningún módulo nuevo consulta estas tablas ni tablas privadas Identity. Los fixtures positivos viven solo en bases temporales propias, y los errores de integridad se verifican únicamente por SQLSTATE sin imprimir SQL/bindings/coordenadas. Desarrollo no recibe identidad/permisos ni datos comerciales.

La futura administración sensible requerirá autorización de servidor/MFA, auditoría, correlación, idempotencia/concurrencia y outbox si publica eventos conforme al maestro; no se concede ese acceso por esta base. Estado draft no reemplaza las acciones operativas de las pantallas T-03/A-06. [ADR-007](../decisions/ADR-007-marketplace-commercial-foundation.md), [plan](../../sprints/sprint-02/checkpoint-02g-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02g-evidence.md).

## Lectura técnica por consola — 02H

DP-033 autoriza únicamente el diagnóstico local/testing con acceso técnico al entorno de desarrollo existente. No agrega ruta HTTP, autenticación, capacidad, grant, membresía ni resolución global de recursos. La coincidencia entre borradores no concede permisos. Provider no registra comando/puerto fuera de local/testing; comando y adaptador deniegan antes de DI/SQL si cambia el entorno.

Los tres ULID se validan estrictamente antes de resolver servicios. Una sentencia parametrizada exige alcance merchant/market/branch exacto y draft en las tres filas, sin seleccionar nombres, ubicación, fiscalidad o identificadores internos. Salida solo versión y matched/not_found; referencias desconocidas y relaciones cruzadas son indistinguibles. Entrada inválida, entorno denegado y error de infraestructura usan mensajes fijos sin eco, SQL, bindings o stacktrace. No se imprimen consultas ni valores desde los listeners de prueba. [Plan](../../sprints/sprint-02/checkpoint-02h-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02h-evidence.md).
