# Decisiones pendientes

Las decisiones técnicas aprobadas para Sprint 00 se trasladaron a ADR. Este registro conserva únicamente asuntos todavía abiertos. El usuario asignó el 4 de octubre de 2026 a Nilton como responsable de las 13 decisiones abiertas, con revisión el 11 de octubre de 2026. La fecha es de revisión; no implica que las reglas o proveedores estén aprobados.

## Negocio pendiente

| ID | Decisión | Contexto | Bloquea | Responsable | Fecha de revisión |
|---|---|---|---|---|---|
| DP-001 | Mercado piloto | Distritos y horarios de lanzamiento | Markets/zones | Nilton | 2026-10-11 |
| DP-002 | Modelo inicial de cobro | Tarifa cliente, comisión y subsidios | Pricing/Settlements | Nilton | 2026-10-11 |
| DP-003 | Modelo de flota | Propia, terceros, independientes o mixto | Logistics | Nilton | 2026-10-11 |
| DP-004 | Pago y proveedor inicial | Pasarela, Yape/Plin, efectivo/contraentrega | Payments | Nilton | 2026-10-11 |
| DP-005 | Facturación | Emisor y separación de cargos | Payments/Settlements | Nilton | 2026-10-11 |
| DP-006 | Política de cancelación | Ventanas, responsables y costos | Ordering/Payments | Nilton | 2026-10-11 |
| DP-007 | Verificación de edad | Procedimiento y rechazo | Ordering/Logistics | Nilton | 2026-10-11 |
| DP-008 | Valores de SLA | Aceptación, preparación, búsqueda y entrega | Platform/Logistics | Nilton | 2026-10-11 |
| DP-009 | Modelo de soporte | Canales, horarios, escalamiento y compensaciones | Support | Nilton | 2026-10-11 |
| DP-010 | Proveedores externos productivos | Mapas, mensajería, storage, identidad, correo e invoicing | Adaptadores productivos | Nilton | 2026-10-11 |

## Técnica pendiente fuera de Sprint 00

DP-001 conserva su bloqueo para mercados y zonas operativas reales. El 10 de octubre de 2026 el usuario eligió «Cobertura ficticia local (recomendado)» para continuar después de 01F. Esta excepción permite únicamente el ejercicio técnico 02A con polígonos sintéticos, sin persistencia de mercados/zones, distritos, horarios, tarifas ni operación de lanzamiento. No resuelve DP-001 ni cambia su responsable/fecha.

| ID | Decisión | Contexto | Bloquea | Responsable | Fecha de revisión |
|---|---|---|---|---|---|
| DP-012 | Herramientas concretas de frontend | Frontend continúa fuera de Sprint 00 | Apps web futuras | Nilton | 2026-10-11 |
| DP-013B | CD y hosting | Proveedor, ambientes, secretos, despliegue y rollback | Despliegue futuro | Nilton | 2026-10-11 |
| DP-022 | RTO/RPO iniciales | Requiere objetivos de negocio y capacidad operativa | Recuperación productiva | Nilton | 2026-10-11 |

## DP-023 — Autorización técnica de Checkpoint 00C

- **Estado:** resuelta por aprobación explícita del usuario el 3 de octubre de 2026.
- **Detectada:** 3 de octubre de 2026.
- **Fuentes:** S00-006 exige acceso protegido a Horizon; S00-007 exige conexión autenticada, canal privado y pruebas positivas/negativas. S00-011 incorpora Sanctum en 00D y la identidad funcional está fuera del Sprint 00.
- **Ambigüedad:** no se especifica el mecanismo ni el actor autorizado para los accesos técnicos positivos de 00C antes de contar con la base de autenticación de 00D.
- **Decisión requerida:** autorizar una credencial técnica exclusiva del entorno local, sin usuarios ni capacidades comerciales, o definir otro mecanismo aprobado para 00C.
- **Bloquea:** implementación de autorización de Horizon y canal privado Reverb; sus pruebas positivas y cierre de S00-006/S00-007. No autoriza adelantar Sanctum ni Identity.
- **Seguridad:** no implementar un bypass por `APP_ENV=local`, un dashboard público ni una autorización positiva ficticia para resolver esta omisión.
- **Resolución aprobada:** credencial técnica exclusiva de desarrollo en `.env.docker` ignorado; HTTP Basic para Horizon y autorización únicamente de `private-technical.v1`. No crea usuarios, capacidades comerciales ni Sanctum. Entorno local por sí solo nunca autoriza; fuera de local se deniega incluso con credencial válida.

## DP-024 — Distribución reproducible de MinIO local

- **Estado:** resuelta por aprobación explícita del usuario el 3 de octubre de 2026.
- **Detectada:** 3 de octubre de 2026.
- **Fuente:** ADR-003 aprueba MinIO local, pero no define una distribución ni versión concreta.
- **Evidencia:** `docker manifest inspect minio/minio:RELEASE.2025-09-07T16-13-09Z` devuelve `denied`/`unauthorized`; la misma etiqueta en `quay.io/minio/minio` devuelve `no such manifest` desde esta máquina.
- **Decisión requerida:** identificar una distribución oficial accesible y mantenida con versión/digest verificables, o aprobar explícitamente una alternativa de distribución. Estas dos consultas no demuestran que todas las versiones o registros sean inaccesibles.
- **Bloquea:** selección de imagen e integración reproducible de MinIO en S00-008 y comprobación completa de S00-009.
- **Restricción:** no sustituir silenciosamente MinIO por otro producto, una imagen de terceros o una versión histórica sin evaluar soporte y seguridad.
- **Resolución aprobada:** construir imagen local desde código oficial fijado a commits; exclusivamente desarrollo. MinIO `9e49d5e7a648f00e26f2246f4dc28e6b07f8c84a` y mc `7394ce0dd2a80935aded936b09fa12cbb3cb8096`. Upstream archivado: esta distribución no constituye elección de proveedor ni recomendación productiva. Referencia: [repositorio oficial](https://github.com/minio/minio).

## Decisiones retiradas por aprobación

DP-011, DP-013A, DP-014, DP-014A y DP-015 a DP-021 quedaron resueltas mediante ADR-001 a ADR-005. Los ADR sustituyen únicamente decisiones técnicas; no modifican el documento maestro v1.6 ni sus reglas de negocio.

## DP-025 — Concreción de Identity local / OTP

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026.
- **Fuente:** maestro v1.6 §§35, 59–60 y 71; alcance Identity backend local elegido por el usuario.
- **Vacío:** no se fijan longitud/TTL OTP, intentos, reenvío, entrega de desarrollo, normalización de celular, estados habilitados ni versión/texto de consentimiento para el checkpoint.
- **Parámetros aprobados:** OTP de 6 dígitos, TTL 5 minutos, máximo 5 intentos, reenvío tras 60 segundos invalidando el código anterior; entrega exclusivamente local mediante adaptador restringido, sin SMS real ni OTP en logs. El usuario respondió «si apruebo» a esta propuesta y al cierre de 00F.
- **Definiciones aprobadas:** celular en formato internacional E.164 sin limitar país; estados iniciales active/blocked; consentimiento ficticio local-v1, exclusivo de desarrollo y con aceptación explícita. No equivale a términos legales productivos.
- **Seguimiento:** decisión resuelta directamente por el usuario el 10 de octubre de 2026; no queda una asignación abierta por completar.
- **Bloqueo resuelto:** implementación local del flujo OTP y sus datos/consentimientos en 01A puede avanzar bajo estas definiciones. Proveedores y operación productiva permanecen fuera del alcance.

## DP-026 — Precedencia y alcance de autorización

- **Estado:** resuelta por aprobación explícita del usuario («aprueba») el 10 de octubre de 2026.
- **Fuente:** maestro v1.6 §§2.1, 35 y 60: capacidad + alcance + recurso, roles con scope y excepciones allow/deny con expiración.
- **Ambigüedad:** no fija precedencia allow/deny, herencia entre scopes ni tratamiento exacto del límite de expiración.
- **Reglas aprobadas:** deny por defecto; actor bloqueado denegado; deny explícito vigente prevalece; scopes exactos sin herencia ni comodines; expiración now >= expiry UTC; contexto de recurso resuelto por servidor y coincidente, desconocido denegado.
- **Alcance:** núcleo puro y pruebas con fixtures; sin privilegios administrativos reales ni endpoints de asignación.
- **Bloqueo resuelto:** implementación del núcleo y contratos de 01B con fixtures; no autoriza privilegios reales ni altera 01A.
- **Seguimiento:** resuelta directamente por el usuario; sin asignación abierta.
- **Plan revisable:** docs/sprints/sprint-01/checkpoint-01b-plan.md.

## DP-027 — Selección geoespacial del ejercicio local

- **Estado:** resuelta para el alcance ficticio por aprobación explícita del usuario el 10 de octubre de 2026.
- **Fuente:** maestro v1.6 §§32–34.1: PostGIS, cobertura por polígonos y selección por prioridad/regla vigente. No fija inclusión de bordes ni resolución de empates.
- **Ambigüedad:** implementar esos criterios silenciosamente cambiaría la selección de cobertura. Se mantuvo detenida la implementación afectada mientras se preparaba el alcance técnico.
- **Reglas aprobadas:** WGS84/SRID 4326, longitud/latitud; bordes incluidos; mayor prioridad primero; rechazo si dos zonas de máxima prioridad empatan. El usuario respondió «Aprobar criterio local (recomendado)» a esa propuesta.
- **Alcance:** 02A, núcleo puro, adaptador PostGIS de lectura con polígonos sintéticos y comando técnico exclusivamente local/testing. No habilita cobertura comercial, horarios, tarifas ni mercados reales. Las reglas productivas siguen condicionadas por DP-001 y la especificación operativa correspondiente.
- **Referencias técnicas:** [ST_Covers 3.5](https://postgis.net/docs/manual-3.5/ST_Covers.html), [ST_MakePoint 3.5](https://postgis.net/docs/manual-3.5/ST_MakePoint.html). Documentan el predicado inclusivo y X=longitud/Y=latitud; no sustituyen aprobación de reglas de negocio.
- **Continuidad técnica:** tras aprobar/publicar 02A, el usuario solicitó continuar. 02B reutiliza estos criterios y los mismos polígonos mediante una sonda HTTP local de solo lectura. No modifica la decisión ni habilita mercados/zones operativos; DP-001 permanece abierta.

## DP-028 — Base geográfica vacía y propiedad de datos

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026; detectada al continuar después de 02B.
- **Fuente:** maestro v1.6 §§32–34, 54–57. Define countries/markets/service_zones y estados controlados, pero no enumera estados de mercado/zona ni zone_type. La agrupación countries/markets en oleada base Platform no fija inequívocamente su dueño modular.
- **Límite vigente:** DP-001 bloquea mercados/zones operativos; la excepción ficticia de 02A/02B no autoriza persistirlos. No se puede ampliar ese alcance ni elegir estados o propiedad silenciosamente.
- **Propuesta concreta:** Marketplace dueño de countries, markets y service_zones; tres tablas inicialmente vacías, sin seeds, administración ni activación; mercados/zonas solo draft y zone_type solo fixture; bigint/ULID, FK restrict e índices, polygon geography(MultiPolygon,4326) válido/no vacío/2D. Resto de campos/checks detallado en el plan.
- **Resolución aprobada:** el usuario respondió «Aprobar plan 02C (recomendado)» a la creación de estas tablas vacías, propiedad de Marketplace, mercados/zonas solo draft, zone_type=fixture y PostGIS SRID 4326, sin seeds ni activación real. [ADR-006](ADR-006-marketplace-geographic-foundation.md) registra propiedad y restricciones aprobadas.
- **Bloqueo resuelto:** implementación y aplicación local aditiva de la base 02C, con pruebas aisladas previas. La aprobación de datos no autorizaba publicación; después de presentar la evidencia terminada, el usuario respondió «Apruebo y autorizo» a la aprobación final de 02C y su commit, push y CI el 10 de octubre de 2026. Véase [registro de aprobación](../../sprints/sprint-02/checkpoint-02c-evidence.md#aprobación-y-publicación-autorizadas).
- **No resuelve:** DP-001, reglas/tipos/estados operativos, país/moneda/timezone del piloto, horarios, zone_rules ni proveedores productivos.
- **Plan revisable:** [Checkpoint 02C](../../sprints/sprint-02/checkpoint-02c-plan.md).
- **Seguimiento:** resuelta directamente por el usuario; no queda una nueva asignación abierta.

## DP-029 — Diagnóstico local de borradores geográficos persistidos

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026; detectada al continuar después de 02C.
- **Fuente:** maestro v1.6 §§32–34.1, 55–56 y 64; DP-027, DP-028 y ADR-006. La base aprobada está vacía y las sondas existentes no seleccionan ni consultan sus borradores.
- **Ambigüedad:** la aprobación de persistencia vacía no define una consulta diagnóstica de drafts ni autoriza tratarlos como cobertura operativa. DP-027 se concretó con fixtures geometry planos; aplicar ese criterio a geography persistente requiere explicitar la evaluación geodésica, sin sustituir silenciosamente el comportamiento anterior.
- **Propuesta concreta:** nuevo comando exclusivamente local/testing, de solo lectura, por ULID de mercado draft; consulta únicamente sus zonas draft/fixture mediante ST_Covers sobre geography nativa SRID 4326; bordes incluidos, máxima prioridad y empate máximo rechazado. Resultado técnico local-persisted-coverage-v1 con market_not_found/outside/selected/ambiguous y ULID de zona nullable. Datos persistidos de prueba solo en bases temporales propias; desarrollo sigue vacío. No agrega seeds, API, administración, activación ni transiciones.
- **Resolución aprobada:** el usuario respondió «Aprobar plan 02D (recomendado)» a la propuesta de consola local/testing, mercado por ULID, zonas draft/fixture, geography nativa, bordes incluidos, máxima prioridad y rechazo de empates; sin escrituras, seeds, API ni activación. Desarrollo permanece vacío y los fixtures persistidos viven solo en bases temporales de prueba.
- **Bloqueo resuelto:** implementación del puerto/caso de uso/adaptador y comando de lectura persistida de 02D bajo ese alcance diagnóstico. Selected no hace elegible el borrador para operación real.
- **Concreción técnica:** se complementa ST_Covers con ST_DWithin(..., 0) sobre geography nativa para cumplir bordes incluidos: en PostGIS 3.5.7 el caso sintético de borde de hueco devuelve Covers=false y distancia cero. No se agrega distancia positiva ni se altera la regla aprobada. El plan documenta reproducción, fuentes y pruebas a ambos lados del borde.
- **No modifica:** sondas 02A/02B ni su contrato local-coverage-v1, constraints draft/fixture de 02C, DP-001, estados/tipos operativos, proveedores o reglas de checkout.
- **Plan revisable:** [Checkpoint 02D](../../sprints/sprint-02/checkpoint-02d-plan.md).
- **Seguimiento:** resuelta directamente por el usuario; no queda una nueva asignación abierta. La aprobación de DP-029 no autorizaba publicación; después de presentar la evidencia terminada, el usuario respondió «Apruebo y autorizo» a la aprobación final de 02D y su commit, push y CI el 10 de octubre de 2026. Véase [registro de aprobación](../../sprints/sprint-02/checkpoint-02d-evidence.md#aprobación-y-publicación-autorizadas).

## DP-030 — Acceso HTTP autorizado al diagnóstico persistido local

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026; detectada al continuar después de 02D.
- **Fuente:** maestro v1.6 §§32–34.1 y 58–60; DP-026 a DP-029 y ADR-006. 02D permite consola, excluye API nueva; 02B expone únicamente polígonos inline. La aprobación previa no define acceso HTTP a borradores privados.
- **Ambigüedad:** no se ha autorizado qué actor puede consultar esos borradores por HTTP ni la capacidad/scope/recurso de ese diagnóstico. Una sesión o APP_ENV local no concede permiso por sí sola; tampoco se amplía silenciosamente la credencial de Horizon.
- **Propuesta concreta:** GET local/testing `/api/v1/marketplace/local-persisted-coverage-probe`, sesión cookie local de Identity y capacidad exacta `marketplace.local.persisted_coverage.read`, scope platform sintético fijo y recurso técnico fijo resueltos por el servidor; DP-026 íntegra. Autoriza la lectura técnica de cualquier mercado draft indicado por ULID, restringiendo cada consulta a ese mercado y sus zonas draft/fixture. No constituye autorización operativa por mercado.
- **Datos y límites:** desarrollo conserva sus tablas geográficas vacías y no recibe usuarios/permisos nuevos; acceso positivo y borradores únicamente en bases temporales propias de prueba. Sin escrituras de negocio, seeds, API pública de borradores, administración, activación ni operación real. Metadatos técnicos de sesión/limitador pueden actualizarse.
- **Resolución aprobada:** el usuario respondió «aprobar y continuar» a la propuesta de 02E y a su mecanismo de sesión/capacidad/scope/recurso exactos, denegación por defecto y aislamiento por mercado, sin nuevos usuarios/permisos ni datos geográficos en desarrollo. Aprobar permite implementar y validar; no autoriza publicación.
- **Bloqueo resuelto:** implementación de la ruta y adaptación de autorización según el plan. DP-001 permanece abierta.
- **Plan revisable:** [Checkpoint 02E](../../sprints/sprint-02/checkpoint-02e-plan.md), con referencias exactas, composición entre módulos, respuestas, límites y validaciones. Commit/push/CI se solicitarán con el resultado terminado.
- **Seguimiento:** resuelta directamente por el usuario; sin nueva asignación operativa ni cambio de responsable/fecha de DP-001. Después de presentar el resultado terminado, el usuario respondió «continuar, aprobado» a la aprobación final de 02E y a su commit/push/CI el 10 de octubre de 2026; véase [registro de aprobación](../../sprints/sprint-02/checkpoint-02e-evidence.md#aprobación-y-publicación-autorizadas).

## DP-031 — Primera escritura transaccional de fixtures geográficos

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026; detectada al continuar después de 02E.
- **Fuente:** maestro v1.6 §§33–34.1, 49–50, 55–56 y 58–60; ADR-002/ADR-006 y DP-026 a DP-030. Las interfaces geográficas aprobadas son de lectura; las tablas se mantienen vacías en desarrollo. ADR-002 exige concretar scope/actor, expiración, retención y respuesta antes de adoptar idempotencia en otro dominio.
- **Vacío original:** no se había autorizado una mutación geográfica, su permiso, perfiles permitidos, auditoría ni política de idempotencia. Una sesión o permiso read no concede create. La aprobación anterior no permite poblar desarrollo ni conceder privilegios administrativos.
- **Propuesta aprobada:** POST local/testing `/api/v1/marketplace/local-draft-fixtures`, sesión cookie/CSRF y capacidad exacta create independiente de read, scope/recurso técnicos de servidor. Dos perfiles fijos A/B crean un mercado draft y tres zonas draft/fixture por operación, con país sintético ZZ compartido/reutilizable solo si coincide. Sin nombres, coordenadas o configuración operativa arbitrarios del cliente.
- **Controles aprobados:** idempotencia 24 horas desde primer claim, sin extensión/purga/reuso automático; scope/actor/key exactos y fingerprint canónico mediante contrato público de Platform. Transacción única, concurrencia, rollback completo, correlación y registro privado Marketplace append-only con snapshot inmutable. Sin eventos públicos/efectos externos en este ejercicio: no se agrega outbox ni consumidor sin uso.
- **Datos y límites:** una tabla de operaciones nueva, vacía y forward-only; aplicación local aditiva después de pruebas. Desarrollo conserva vacías todas las tablas geográficas/operaciones y no recibe usuarios/permisos/grants nuevos. Creaciones y accesos positivos solo en bases temporales propias de prueba. Sin edición, importación, administración, activación o operación real; DP-001 abierta.
- **Bloqueo resuelto:** el usuario respondió «aprobado, continuar.» al plan 02F. Autoriza la ruta, permiso, idempotencia, auditoría y migración local vacía bajo todos los límites del plan; commit/push/CI se solicitarán con evidencia terminada.
- **Plan revisable:** [Checkpoint 02F](../../sprints/sprint-02/checkpoint-02f-plan.md), con datos exactos, contrato, permisos, política de replay/retención, tabla y pruebas. Commit/push/CI se solicitarán con el resultado terminado.
- **Seguimiento:** resuelta directamente por el usuario; sin cambiar responsable/fecha de DP-001 ni resolver decisiones productivas. Después de presentar el resultado terminado, el usuario aprobó el cierre y autorizó commit/push/CI mediante «Apruebo y autorizo» el 10 de octubre de 2026; véase [registro de aprobación](../../sprints/sprint-02/checkpoint-02f-evidence.md#aprobación-y-publicación-autorizadas).
