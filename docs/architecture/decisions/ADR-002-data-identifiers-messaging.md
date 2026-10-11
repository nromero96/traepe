# ADR-002 — Datos, identificadores y mensajería

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** PostgreSQL con PostGIS; bigint interno; `public_id` ULID único e indexado; outbox/inbox técnico.

## Reglas

- IDs internos bigint nunca se exponen públicamente.
- Recursos públicos usan ULID único e indexado.
- PostgreSQL es fuente de verdad; PostGIS administra geodatos.
- Outbox mantiene `event_id` y payload inmutables; sus metadatos de publicación pueden cambiar.
- Inbox deduplica por `event_id` mediante constraint único.
- La cola entrega al menos una vez; jobs y consumidores son idempotentes para evitar efectos duplicados.
- Sprint 00 no implementa consumidores comerciales.

## Consecuencias

Migraciones, contratos y pruebas deben verificar integridad, concurrencia, deduplicación y no exposición de IDs internos.

## Concreción técnica de 00E — 3 de octubre de 2026

El usuario aprobó 00D y autorizó 00E. Platform mantiene tablas privadas prefijadas para claves, outbox, inbox y operación/efecto ficticios. PostgreSQL garantiza bigint interno, ULID público válido/único, hashes SHA-256 válidos, estados, FK e índices de las consultas reales. Un trigger rechaza actualizar contenido/identidad del outbox o eliminar filas; solo los campos de entrega son mutables. El envelope se cifra con la clave ignorada de Laravel y su hash es inmutable.

Idempotencia identifica `(scope_hash, actor_hash, key_hash)` y recibe expiración UTC explícita del llamador. Solo se almacena un fingerprint y una referencia pública, nunca la petición ni una respuesta HTTP sensible. La reserva, cambio ficticio, evento y referencia se confirman en una transacción; concurrencia usa unicidad y bloqueo de fila. Expiración no reutiliza ni borra automáticamente la clave. Las políticas comerciales de scope/actor, expiración, retención y resolución de respuesta requieren especificación antes de adoptar estas primitivas en un dominio.

Inbox conserva tanto `UNIQUE(event_id)` aprobado como `UNIQUE(source,message_id)` exigido por el maestro §45. Deduplicación y efecto ficticio se guardan en la misma transacción. Publicación mediante job Redis/Horizon ocurre antes de marcar entrega; un crash entre ambos puede redeliver. `FOR UPDATE SKIP LOCKED` evita que dos dispatchers seleccionen el mismo lote simultáneamente; no se afirma entrega exactamente una vez.

La migración es aditiva, transaccional y específica de PostgreSQL; rollback se verificó exclusivamente en bases temporales propias. Las tablas ficticias permiten demostrar atomicidad/efecto único sin anticipar Ordering, Payments ni consumidores comerciales. No definen finanzas, auditoría, retención productiva ni migraciones de otros módulos.

## Adopción local por Marketplace — 02F, 10 de octubre de 2026

DP-031 y el [plan 02F](../../sprints/sprint-02/checkpoint-02f-plan.md) fueron aprobados explícitamente por el usuario. La identidad de idempotencia usa scope marketplace.local_draft_fixture.create.v1 con las referencias técnicas platform/recurso del plan, actor identity.user:<ULID verificado por Identity> y clave ASCII 1–255 sin espacios/control. El fingerprint canónico contiene fixture_profile y schema_version=1. Expira 24 horas desde el primer claim; replay conserva expiry; al alcanzar el límite rechaza, sin purga, extensión ni reuso automático. No constituye política productiva de retención.

Platform guarda hashes y referencia pública mediante su puerto Application; Marketplace resuelve esa referencia exclusivamente por actor en su historial privado append-only y devuelve el snapshot validado original. Claim, país/mercado/zonas e historial comparten la misma transacción/conexión. Sin evento público ni efecto asíncrono asociado a esta creación técnica, no se añade outbox ni consumidor sin uso. Una futura mutación operativa con eventos requiere contrato versionado/outbox aprobado. [Evidencia](../../sprints/sprint-02/checkpoint-02f-evidence.md).

## Alta comercial local — 02I, 10 de octubre de 2026

DP-034 aprobada adopta el puerto público IdempotencyStore para scope marketplace.local_draft_commerce.create.v1:<scope técnico>:<recurso create> y actor identity.user:<ULID verificado>. Clave ASCII !–~ de 1–255 bytes, sin espacios/control; TTL 24h UTC desde primer claim, sin extensión, purga ni reuso. Fingerprint canónico schema_version=1 y entrada validada exacta; coordenadas se codifican como strings de los float validados, igualando enteros/decimales equivalentes y cero negativo con cero. RequestFingerprint sigue rechazando floats; no se modifica su regla transversal.

Callback revalida create, bloquea Market draft y confirma claim, Merchant, Branch e historial append-only en la misma conexión/transacción. Fallos revierten todo. Platform almacena solo hashes y referencia pública; Marketplace guarda el snapshot v1 cifrado en su diario privado, verifica hash y forma cerrada al restaurarlo, y restringe lectura al creador con read vigente. La correlación del diario conserva la primera solicitud; cada respuesta lleva la correlación actual. Replay exige create vigente y devuelve el snapshot original sin leer perfiles actuales ni ampliar TTL. No se promete serialización con una futura administración concurrente de grants. Sin evento/efecto externo en este ejercicio, no se agrega outbox sin uso ni política productiva de retención. [Plan](../../sprints/sprint-02/checkpoint-02i-plan.md) y [evidencia](../../sprints/sprint-02/checkpoint-02i-evidence.md).

## Adopción por Catalog — 03A

[ADR-008](ADR-008-catalog-local-draft-foundation.md) autoriza el consumidor local de IdempotencyStore: scope catalog.local_draft_catalog.create.v1 con scope/recurso técnicos, actor identity.user:<ULID>, fingerprint exacto v1 y TTL 24h. Claim, bloqueo público Marketplace, catálogo/producto y diario cifrado propio se confirman juntos. Replay no extiende el claim y exige permiso create actual; GET no consulta idempotencia. No existe evento ni efecto externo para este caso de uso.
