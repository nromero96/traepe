# Plan aprobado de Checkpoint 02F — Creación transaccional de fixtures geográficos

Fecha: 10 de octubre de 2026. **Estado: plan y checkpoint aprobados explícitamente por el usuario; implementación validada localmente y publicación/CI autorizadas.**

02E está publicado en `79d4d3d76c3a4c60075aabf596d3207be333d42a`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38084025841), incluidos calidad, integración, reinicio/persistencia y limpieza. El usuario solicitó continuar. Hasta 02E, las interfaces de Marketplace solo realizan lecturas y desarrollo conserva sus tablas geográficas vacías.

## Decisión requerida y propósito

Maestro v1.6 §§33–34.1, 49–50, 55–56 y 58–60; DP-026 a DP-030 y ADR-002/ADR-006. Crear borradores exige aprobar esa ampliación de escritura, su permiso y la auditoría. [ADR-002](../../architecture/decisions/ADR-002-data-identifiers-messaging.md) exige especificar scope/actor, expiración, retención y resolución de respuesta antes de adoptar idempotencia en otro dominio. La lectura aprobada no concede escritura.

DP-031 propone una primera mutación técnica local: crear de forma atómica un conjunto sintético fijo, ejercitar idempotencia/concurrencia y después leerlo con el diagnóstico existente. Desarrollo seguiría vacío; casos positivos solo en bases temporales propias de prueba. El usuario aprobó el plan mediante «aprobado, continuar.» el 10 de octubre de 2026; DP-031 autoriza esta implementación local.

No se propone todavía un editor de mercados, importación de mapas, activación ni un endpoint comercial. Un perfil fijo permite comprobar el ciclo de escritura sin aceptar coordenadas, países o reglas operativas del cliente.

## API y perfiles propuestos

POST `/api/v1/marketplace/local-draft-fixtures`, únicamente local/testing. JSON UTF-8 con una sola propiedad requerida:

```json
{"fixture_profile":"synthetic-origin-a-v1"}
```

Se admiten dos variantes fijas: synthetic-origin-a-v1 y synthetic-origin-b-v1. Solo cambia el nombre del mercado, Synthetic Market A/B. Ambas usan los siguientes datos, definidos por servidor; no admiten nombres, ULIDs, polígonos, prioridad, moneda, timezone, estados o permisos del cliente.

| Elemento | Datos sintéticos |
|---|---|
| País reutilizable | code ZZ, name Synthetic Country, currency_code ZZZ |
| Mercado nuevo | nombre según perfil, timezone Etc/UTC, currency_code XXX, status draft, version 1 |
| Zona A nueva | Synthetic Zone A, priority 10; polígono exterior (0,0)–(4,4), hueco (1,1)–(2,2) |
| Zona B nueva | Synthetic Zone B, priority 20; polígono (3,0)–(6,2) |
| Zona C nueva | Synthetic Zone C, priority 20; polígono (5,1)–(7,3) |

Las coordenadas/anillos son exactamente los WKT del fixture inline 02A, conservando su definición. Se almacenan como geography(MultiPolygon,4326) mediante conversión explícita de esos Polygon sintéticos; la evaluación posterior sigue siendo la geography nativa de 02D/02E. Cada mercado/zona recibe ULID nuevo generado por servidor; no se reutilizan los IDs inline ni se modifica la sonda 02A/02B.

Si no existe country code ZZ, se crea dentro de la transacción. Si existe y coincide exactamente en name/currency_code con el perfil sintético, se reutiliza conservando sus datos/ULID. Si no coincide, se rechaza con 409 fixture_country_conflict; no se sobrescribe. El código único y el bloqueo aseguran un solo país compartido al crear varios conjuntos concurrentes. No se impone unicidad nueva de nombres de mercado/zona.

## Acceso y orden de ejecución

Sesión cookie del flujo local Identity y CSRF en POST; web/auth:web, sin bearer/basic ni ampliación de credencial Horizon. Capacidad nueva exacta `marketplace.local.draft_fixture.create`, scope platform técnico `01ARZ3NDEKTSV4RRFFQ69G5FB2` y recurso `marketplace.local.draft_fixture.create` / `01ARZ3NDEKTSV4RRFFQ69G5FB4`, resueltos por servidor. Son fixtures técnicos; no permisos administrativos productivos. El permiso read de 02E no concede create, ni create concede read.

DP-026 completa: deny por defecto, actor activo, deny vigente prevalece, scope exacto sin herencia y expiración UTC al alcanzar el instante. Actor público obtenido por contrato de Identity a partir de la sesión verificada. Nunca desde JSON, cabeceras o parámetros del cliente. Sin usuarios, roles o grants nuevos en desarrollo; permiso positivo únicamente en bases temporales de prueba.

Gate de entorno antes de sesión/CSRF/autorización/DI/SQL, incluso con rutas cacheadas en local. Fuera de local/testing: 404; bindings positivos ausentes y adaptadores deniegan antes de consultas. La autorización precede validación de perfil y cualquier claim de idempotencia. Se revalida inmediatamente antes de crear dentro del callback transaccional; un rechazo revierte la reserva y los cambios. Cada replay exige autorización vigente antes de resolver la referencia almacenada. No se promete serialización con una futura administración concurrente de grants, que permanece fuera de este ejercicio.

Limitador propio de 30 solicitudes/minuto por actor, separado de OTP y de los diagnósticos. No-store/private, correlación y logs técnicos actuales sin body, cabeceras, claves, cookies, SQL o coordenadas. JSON mal formado, perfil inválido o propiedades adicionales se rechazan con 422 genérico, sin reflejar campos/valores arbitrarios. Medio no JSON: 415 unsupported_media_type; incorporar únicamente ese código transversal al renderer estándar. CSRF inválido: 419; una solicitud con CSRF válido sin sesión: 401; permiso denegado: 403. Esos controles no se omiten para obtener un error posterior.

## Idempotencia y transacción

- Idempotency-Key requerida: 1–255 caracteres ASCII imprimibles sin espacios/control; no normalizarla ni guardarla/imprimirla en claro. Entrada inválida: 422 antes de reservar.
- Identidad exacta: scope `marketplace.local_draft_fixture.create.v1:<scope-public-id>:<resource-public-id>`, actor `identity.user:<ULID-servidor>` y key. Platform guarda los tres hashes mediante su contrato Application IdempotencyStore; Marketplace no consulta platform_idempotency_keys directamente.
- Fingerprint canónico del JSON validado fixture_profile y schema_version=1. Ambas variantes válidas permiten verificar mismatch con la misma clave. Otras cabeceras, IDs o datos no forman un contexto de autorización.
- Expiración fija técnica: 24 horas desde el primer claim, con reloj UTC del servidor. Replay no extiende el plazo. Después del límite: 409 idempotency_expired; no purga, borrado ni reutilización automática. Es una política local aprobable, sin retención productiva implícita.
- La misma entrada/actor/scope/key devuelve la operación original; distinto perfil válido bajo esa identidad: 409 idempotency_mismatch. Otro actor o clave constituye otra operación y recibe otro mercado, sin acceder a la respuesta del primero.
- Se reutiliza el bloqueo/unicidad y transacción PostgreSQL del puerto público de Platform, en la misma conexión del monolito. El callback de Marketplace crea/reutiliza país, crea mercado y tres zonas, y guarda el registro de operación. Retorna solo su ULID a Platform. Ante cualquier fallo se revierten claim y escrituras nuevas; un país preexistente se conserva.
- La referencia del replay resuelve exclusivamente una operación propia de Marketplace y de ese actor, usando su snapshot inmutable. No reconstruye la respuesta desde filas mutables ni consulta tablas privadas de otro módulo.

No hay evento público, mensaje externo, job, proyección o notificación asociado a esta creación de fixtures; por tanto no se incorpora outbox ni consumidor sin uso en 02F. La operación confirmada y su historial/auditoría forman un registro append-only en la misma transacción. Una futura mutación operativa con eventos/efectos asíncronos requerirá contrato de evento y outbox aprobado; este ejercicio no lo autoriza ni sustituye.

## Registro de operación y migración propuesta

Una tabla nueva privada de Marketplace, `marketplace_local_fixture_operations`, inicialmente vacía:

| Campo | Tipo / restricción |
|---|---|
| id / public_id | bigint interno; char(26) ULID válido, único e indexado |
| market_id | FK markets restrict, única: un registro de creación por mercado del ejercicio |
| actor_public_id | char(26) ULID válido; referencia pública del contrato Identity, sin FK/consulta sobre users desde Marketplace |
| profile_version | varchar(64), solo los dos perfiles descritos |
| schema_version | smallint, inicialmente 1 con CHECK |
| key_hash / request_hash | char(64), SHA-256 hexadecimal válido; nunca clave o body crudos |
| correlation_id | char(26) ULID válido del primer cambio confirmado |
| response_snapshot | jsonb requerido, objeto; forma cerrada y referencias públicas validadas por DTO/contrato antes de persistir o devolver |
| created_at | timestamptz UTC, requerido; sin updated_at ni soft delete |

Índices para actor_public_id/created_at; FK e índice único market_id para resolución de operación. Trigger propio de Marketplace rechaza UPDATE/DELETE de todo el registro. La migración es aditiva y forward-only; down rechaza destrucción automática. No modifica constraints draft/fixture, prioridades o tablas existentes. No reutiliza triggers/domains privados de otro módulo.

Se probará primero en bases aisladas; solo después se aplicará localmente esa tabla vacía y se comprobarán segunda ejecución idempotente y conteos preservados. No se crean datos ni permisos para ejercer la API en desarrollo, no se hacen resets ni se eliminan volúmenes.

## Respuesta y límites modulares

201 estándar: data.type=local_draft_fixture_operation, data.id=ULID de operación y attributes cerrados con profile_version, country_public_id, market_public_id y zone_public_ids (tres ULID nuevos, en orden A/B/C). meta.correlation_id pertenece a la solicitud actual, también en replay; el registro conserva la correlación original. El replay devuelve el mismo 201 y snapshot de datos, sin nueva operación, mercado o zonas. No devuelve bigint, actor, grants, hashes o clave. OpenAPI y schema del snapshot se versionan.

Domain/Application de Marketplace permanecen puros y definen puertos/DTO utilizados. Infrastructure consume únicamente contratos Application públicos de Identity y Platform, y los DTOs/excepciones públicos necesarios; composición específica, sin sustituir bindings anteriores ni acceder a tablas privadas. ArchitectureTest permitirá referencias exactas desde los adaptadores concretos aprobados, preservando las demás fronteras. Interfaces valida formato, recibe actor de sesión, invoca el caso de uso y transforma errores/resultados.

## Aceptación

1. Perfil cerrado A/B, IDs de servidor, permiso create independiente de read y política DP-026 probados. Entradas ajenas, autoría/capacidad/scope/reloj falsificados, medio/JSON/key inválidos y CSRF rechazados antes de datos o claims correspondientes. Errores genéricos, correlación, privacidad, no-store y tasa independiente.
2. Bases PostgreSQL/PostGIS propias desde cero: primer conjunto crea país/mercado/tres zonas/una operación y un claim; siguiente conjunto reutiliza únicamente país. País incompatible rechaza sin sobrescribir y sin claim completado. Snapshot inmutable, ULIDs/FK/checks/índices y rechazo de UPDATE/DELETE/down; segunda migración sin cambios.
3. Replay devuelve exactamente la operación/snapshot original con correlación de solicitud actual; cambio de perfil con misma identidad produce mismatch; expiración exacta/replay sin extensión, aislamiento de actor/scope y autorización vigente incluso al repetir. No se guarda ninguna clave cruda.
4. Concurrencia en procesos independientes: misma identidad produce un único mercado/operación/tres zonas; claves distintas producen dos conjuntos con un solo país. Fallo inyectado a mitad de creación o antes del historial revierte toda la operación, incluido claim y país nuevo; conserva país preexistente. Bloqueo/revocación observado antes de crear cancela el callback.
5. Proceso HTTP local con CSRF/OTP/cookie reales sobre base temporal: create autorizado, replay, deny/bloqueo/logout/cookie replay y GET 02E con permiso read separado sobre los IDs creados. Verificar selected/outside/ambiguous y geografía de bordes/hueco sin cambiar sondas inline.
6. Procesos production/staging, con/sin caché local real: 404 antes de ejecutar sesión/CSRF, autorización o stores. No bindings positivos fuera del entorno permitido ni escrituras por cabeceras/inputs arbitrarios.
7. Aplicación local de migración vacía conserva datos previos; fundación comprueba cuatro tablas Marketplace vacías, usuarios/permisos preservados, sin bases temporales y ocho servicios saludables. Petición real por Nginx deniega sin sesión/CSRF apropiados; sin cambios de negocio.
8. Pint, PHPStan nivel 8, contratos versionados, Unit/Feature e Integration completas; privacidad, diff/enlaces/secretos, especificación/arquitectura/seguridad/datos/API/evidencia actualizadas. Sin dependencias nuevas, producción, administración, mapas reales, edición, eliminación o activación. DP-001 sigue abierta.

La aprobación explícita de DP-031 autoriza implementar/validar esta creación técnica y aplicar localmente la migración vacía, conservando desarrollo vacío. Con la evidencia terminada, el usuario aprobó además el cierre y autorizó commit/push/CI mediante «Apruebo y autorizo»; véase [registro de aprobación](checkpoint-02f-evidence.md#aprobación-y-publicación-autorizadas). No autoriza operación real ni despliegue productivo.
