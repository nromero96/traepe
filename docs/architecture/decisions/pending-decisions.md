# Decisiones pendientes

## DP-039 — Edición local de variantes draft

- **Estado:** resuelta mediante «Si apruebo» el 11 de octubre de 2026, antes del código; plan completo 03E aprobado.
- **Fuente:** maestro v1.6 §§9, 25, 37, 49–50, 56, 58–60, 64, 72 y 90; ADR-008/DP-026/DP-035 a DP-038.
- **Vacío:** 03D excluyó edición y fijó version=1 en CHECK/colección v1. No se concretaron SKU editable/inmutable, campos/revisiones, no_changes, capacidades, historial ni transición de la colección a revisiones reales. “Continuar” no determina esas reglas ni aprueba una incompatibilidad de contrato silenciosa.
- **Plan completo aprobado:** [plan 03E](../../sprints/sprint-03/checkpoint-03e-plan.md): PUT de cinco campos descriptivos de variante draft propia 03D, SKU inmutable en el ejercicio y GET del cambio original propio. Dos capacidades independientes, If-Match de catálogo, no_changes sin escritura, claim 24h y lock claim → Merchant → Catalog → Product → Variant; tres incrementos +1 y diario cifrado append-only.
- **Contrato aprobado:** mismo GET actual de variantes con respuesta/cursor v2 y revisión real; meta.schema_version=2 también vacío. Página/componente v1 congelados como históricos, consumidores locales actualizados y cursor v1 rechazado 422. OpenAPI 2.0.0 declara la incompatibilidad local; todos los demás paths/componentes/historias/replays previos permanecen originales. No versiones ficticias ni filas revisadas escondidas.
- **Migración aprobada:** nueva tabla catalog_local_draft_variant_changes vacía y solo CHECK product_variants_version_check ampliado de version=1 a >=1; las 37 tablas/filas migrations y demás constraints previos preservados. Aplicación local aditiva tras suites, quince Marketplace/Catalog vacías y 38 tablas no-migrations; segunda ejecución sin cambios.
- **Arquitectura aprobada:** tres consumidores Infrastructure adicionales de los mismos tipos públicos Identity/Platform, trece → dieciséis enumerados; puente Merchant existente, Domain/Application puros, sin SQL privado cruzado/ciclos/Shared vertical o dependencias nuevas.
- **Aprobación recibida:** «Si apruebo» autoriza conjuntamente todas las reglas/contrato v2/código/pruebas/migración local vacía y CHECK ampliado. Commit/push/CI de 03E autorizados mediante «si autorizo» el 11 de octubre de 2026 después de presentar el resultado terminado; no se reutiliza la publicación de 03D.
- **Límites:** solo ficticio local/testing, positivos en DB temporales propias y desarrollo vacío. Sin cambio/reuso de SKU, borrado/restauración, precio/stock/listing, venta/activación, frontend/dependencias/datos reales o exención MFA; criterio SKU local no fija producción. DP-001/DP-002/DP-007/DP-012 y demás productivas conservan responsable/fecha.
- **Responsable:** Nilton; revisión solicitada el 11 de octubre de 2026.
- **Evidencia:** [implementación y validación de 03E](../../sprints/sprint-03/checkpoint-03e-evidence.md): 380 pruebas/12982 aserciones, migración vacía aplicada con 37 tablas previas y demás constraints preservados; [publicación funcional confirmada](../../sprints/sprint-03/checkpoint-03e-evidence.md#publicación-confirmada) en main b0be84e1ccb126059a9fa551a3a98a370547465e, Foundation CI 38170842832/job 114562936788, SHA exactos/cuatro etapas correctas y 380/12982 remotos iguales a locales.

## DP-038 — Variantes draft locales

- **Estado:** resuelta mediante «si apruebo» el 11 de octubre de 2026, antes del código; plan completo 03D aprobado.
- **Fuente:** maestro v1.6 §§9, 25, 32–33, 37, 49–50, 52–60, 64, 72, 78–79 y 90; ADR-008/DP-026/DP-035/DP-036/DP-037.
- **Vacío:** el maestro contempla variantes, SKU único en alcance, nombre, tamaño/unidad/barcode/atributos, pero no concreta el alcance de unicidad, formatos/estados, permisos del ejercicio ficticio, creación/lecturas, revisión del producto/catálogo por alta de variante ni snapshot/cursores. Los planes 03A/03B/03C excluyeron variantes/SKU; su aprobación no permite inventar esas reglas.
- **Plan completo aprobado:** [plan 03D](../../sprints/sprint-03/checkpoint-03d-plan.md): alta de variante draft propia, colección actual paginada y snapshot original; seis campos requeridos, SKU ASCII único por comercio incluido soft-delete, mapa de atributos acotado, tres permisos independientes, If-Match, claim 24h, lock claim → Merchant → Catalog → Product y ambos incrementos +1. Dos tablas inicialmente vacías; reglas de FK/CHECK/append-only/cifrado/cursor y pruebas en el plan.
- **Arquitectura aprobada:** mismos contratos públicos Identity/Platform/Marketplace existentes, tres nuevos consumidores Infrastructure explícitos y puente Merchant reutilizado; Domain/Application puros, Shared/resolver global intactos, sin SQL privado cruzado o nuevas dependencias.
- **Bloqueo resuelto:** «si apruebo» autoriza las reglas completas, código/API/capacidades, nuevas tablas/diario, revisiones y pruebas de 03D; preparación y cierre retrospectivo de 03C verificados previamente.
- **Aprobación recibida:** todas las reglas del plan, implementación/pruebas y aplicación local aditiva de dos tablas vacías. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Autorizado y continuar» el 11 de octubre de 2026; [publicación confirmada](../../sprints/sprint-03/checkpoint-03d-evidence.md#publicación-confirmada).
- **Resultado local:** 51 archivos, 355 pruebas/11260 aserciones, Pint 322/PHPStan 8/contratos correctos; dos tablas vacías aplicadas y 35 previas preservadas, segundo migrate sin cambios. Catorce tablas Marketplace/Catalog vacías, ocho servicios saludables y verify-foundation correcto. Publicación autorizada sobre este resultado terminado.
- **Resultado publicado:** main 268bae47233697f659be7600a4335f5bff702c2c; Foundation CI 38161448476/job 114535038415 con run/job SHA exactos y cuatro etapas correctas. Suites remotas 355/11260; corrección de formato documentada dentro del mismo alcance.
- **Límites:** solo ficticio local/testing; positivos en bases temporales propias y desarrollo vacío. Sin edición/borrado de variantes, precios/stock/listings, venta/activación, frontend/dependencias/datos reales o exención MFA. DP-001/DP-002/DP-007/DP-012 y las demás productivas conservan responsables/fechas.
- **Responsable:** Nilton; revisión solicitada en esta continuidad el 11 de octubre de 2026.
- **Evidencia:** [implementación y validaciones de 03D](../../sprints/sprint-03/checkpoint-03d-evidence.md).

## DP-037 — Edición local de productos draft

- **Estado:** resuelta mediante «aprobar y continuar» el 11 de octubre de 2026, antes de iniciar implementación; plan completo 03C aprobado.
- **Fuente:** maestro v1.6 §§9, 25, 29, 32–33, 37, 49–50, 52–60, 64, 72, 78–80, 90 y 95; ADR-008/DP-026/DP-035/DP-036.
- **Vacío:** 03A/03B aprueban altas y lecturas, no edición. El maestro no concreta reemplazo de campos del ejercicio ficticio, capacidades update/read de cambios, elegibilidad por procedencia, operación sin cambios, incremento de revisión de producto ni snapshot antes/después. No se pueden extender permisos ni mutabilidad silenciosamente.
- **Plan completo aprobado:** [plan 03C](../../sprints/sprint-03/checkpoint-03c-plan.md): PUT reemplaza nombre/descripción/marca de producto draft propio de 03A/03B y GET consulta el cambio original propio. Dos permisos independientes, If-Match de catálogo, idempotencia 24h, no_changes sin escrituras, lock claim → Merchant → Catalog → Product, versiones catálogo/producto +1 y diario cifrado append-only. Una tabla inicialmente vacía; contratos, reglas completas y pruebas en el plan.
- **Arquitectura aprobada:** mismos contratos públicos Identity/Platform/Marketplace ya autorizados, tres nuevos consumidores Infrastructure explícitos y puente Merchant existente; Domain/Application puros, Shared y resolver global intactos, sin SQL privado ajeno/ciclos.
- **Bloqueo resuelto:** el usuario aprobó el plan completo mediante «aprobar y continuar». Autoriza implementación, pruebas y aplicación local aditiva vacía conforme a todas sus reglas, después de la preparación documental verificada.
- **Límites:** ficticio local/testing; desarrollo vacío, positivos en bases temporales propias. Sin borrado/restauración, catálogo renombrado/reasignación, categorías/tipo/restricciones, SKU/precio/stock/listings, publicación/activación, frontend/dependencias, datos reales o excepción MFA. DP-001/DP-002/DP-007/DP-012 y demás productivas permanecen abiertas.
- **Aprobación recibida:** todas las reglas del plan, implementación/pruebas y aplicación local aditiva de una tabla vacía. Después de presentar el resultado terminado, commit/push/CI de 03C fueron autorizados mediante «autorizado» el 11 de octubre de 2026; [registro](../../sprints/sprint-03/checkpoint-03c-evidence.md#aprobación-y-publicación-autorizadas).
- **Resultado local:** implementado y validado, 329 pruebas/9445 aserciones, migración vacía aplicada y 34 tablas anteriores preservadas; doce tablas Marketplace/Catalog vacías y ocho servicios saludables. 44 archivos publicados en main b88add660b33b8a94c612ae56fe6275272634c90; [CI completa correcta y cierre](../../sprints/sprint-03/checkpoint-03c-evidence.md#publicación-confirmada) con run/job SHA exactos y cuatro etapas correctas. [Evidencia](../../sprints/sprint-03/checkpoint-03c-evidence.md).
- **Responsable:** Nilton; revisión solicitada en esta continuidad el 11 de octubre de 2026. No cambia responsabilidades/fechas de decisiones productivas.
- **Evidencia:** [preparación, aprobación y resultado de 03C](../../sprints/sprint-03/checkpoint-03c-evidence.md).

## DP-036 — Productos adicionales y consulta del catálogo actual

- **Estado:** resuelta mediante «Aprobar plan completo 03B (recomendado)»; detectada y aprobada el 10 de octubre de 2026 al continuar después de 03A publicado.
- **Fuente:** maestro v1.6 §§9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; ADR-008/DP-026/DP-035.
- **Vacío:** 03A autoriza exclusivamente catálogo/primer producto e historial original. El maestro no concreta ampliación local, acceso al contenido actual, alta adicional, incremento de versión, forma de If-Match, política de cursor ni diario de esas mutaciones. No se pueden extender permisos o datos de 03A silenciosamente.
- **Propuesta completa:** [plan 03B](../../sprints/sprint-03/checkpoint-03b-plan.md): alta de producto conceptual adicional en catálogo draft propio creado en 03A; perfil/colección actuales y snapshot histórico adicional; sesión/CSRF, tres capacidades independientes, versión esperada y bloqueo, claim Platform 24h, diario cifrado append-only, cursor cifrado ligado a actor/catálogo/revisión y expiración 15 minutos. Una tabla inicialmente vacía e índice keyset; todos los campos, rechazos, contratos y pruebas están en el plan.
- **Arquitectura propuesta:** mismas dependencias públicas ya aprobadas de Catalog → Identity/Platform/Marketplace; autoría de 03A comprobada solo en tablas Catalog, referencia Merchant mediante contrato público existente; sin consulta privada ajena ni nueva dependencia.
- **Bloqueo resuelto:** la aprobación explícita autoriza código/API/capacidades, mutación/versionado, cursor, pruebas y migración local aditiva vacía conforme al plan completo. Preparación y verificación retrospectiva de 03A completadas.
- **Límites:** ficticio local/testing; positivos solo en bases temporales propias, desarrollo vacío y sin grants/usuarios nuevos. No SKU, precios/stock/listings, categorías/restricciones, edición/borrado, frontend/dependencias, datos reales o activación. Mantiene responsables/fechas de decisiones productivas.
- **Aprobación recibida:** plan conjunto, implementación/pruebas y aplicación local aditiva vacía. Tras presentar el resultado terminado, el usuario autorizó separadamente commit/push/CI mediante «Autorizado» el 11 de octubre de 2026; [registro](../../sprints/sprint-03/checkpoint-03b-evidence.md#aprobación-y-publicación-autorizadas), conforme a AGENTS.md.
- **Responsable:** Nilton; revisión solicitada en esta continuidad el 10 de octubre de 2026. No modifica la revisión del 11 de octubre de decisiones productivas anteriores.

## DP-035 — Catálogo local con primer producto conceptual

- **Estado:** resuelta por aprobación explícita del plan completo mediante «Aprobar plan completo 03A (recomendado)» el 10 de octubre de 2026.
- **Fuente:** maestro v1.6 §§3.1, 9, 25, 32, 36–37, 49–50, 52–60, 72 y 78–79; ADR-002/ADR-007 y DP-026/DP-034.
- **Vacíos:** §37 distingue catálogo/producto/variante/listing, pero no fija campos completos/estados ni política inicial de alta/lectura. Falta concretar ownership del comercio ficticio, capacidades/recursos, soft delete sin API de borrado, referencia pública/FK de integridad entre módulos y adopción explícita de los contratos Identity/Platform por Catalog respecto de §52.1.
- **Reglas aprobadas:** [plan completo 03A](../../sprints/sprint-03/checkpoint-03a-plan.md): POST crea catálogo/primer producto conceptual draft para un comercio creado por el mismo actor en 02I; GET snapshot original propio con read independiente. Sesión/CSRF, permisos técnicos, idempotencia 24h, transacción/callback/contrato público Marketplace y diario cifrado append-only. Tres tablas inicialmente vacías, campos/restricciones/soft-delete/FK públicas exactas en el plan; product_type exclusivamente NULL y ningún SKU/precio/stock/venta implícitos.
- **Arquitectura aprobada:** excepción técnica acotada de §52.1 para Infrastructure Catalog → contratos públicos Identity/Platform, además de Marketplace; [ADR-008](ADR-008-catalog-local-draft-foundation.md) documenta la decisión. Domain/Application puros, sin consultas privadas cruzadas/ciclos; FK restrict exclusivamente sobre public_id del Merchant como contrato de integridad documentado, no acceso privado runtime.
- **Bloqueo resuelto:** la aprobación explícita del plan completo autoriza datos, API, permisos, contratos, pruebas y migración local vacía de 03A conforme a las reglas registradas.
- **Trabajo autorizado:** implementar/validar el bloque completo y aplicar localmente tres tablas vacías; sin grants ni usuarios de desarrollo. El usuario autorizó commit/push/CI de 03A mediante «Si autorizo» después de revisar el resultado terminado.
- **Límites:** ficticio local/testing, sin administración sensible/MFA real, activación, datos reales, frontend/dependencias, catálogo público, SKU, precios, inventario, categorías/restricciones/venta ni resolución de decisiones productivas. Conserva responsables/fechas de DP-001/DP-002/DP-007/DP-012 y demás decisiones abiertas.

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

## DP-032 — Base local vacía de comercios y sucursales

- **Estado:** resuelta por aprobación explícita del usuario el 10 de octubre de 2026; detectada al continuar después de 02F.
- **Fuente:** maestro v1.6 §§3.1, 9, 32–34, 36, 52–56, 60 y 72; oleada Marketplace de §54 y épicas/historias de §§78–79. DP-028 a DP-031 autorizan geografía/fixtures, sin entidades comerciales.
- **Vacío:** §36 define merchants/branches y campos esenciales, pero no sus estados iniciales, tax_id (formato/unicidad/tratamiento) ni niveles de riesgo. §57 no incluye esos enums. No se puede inferir activación, riesgo favorable ni identificación fiscal desde el alcance ficticio aprobado.
- **Propuesta aprobada:** dos tablas privadas vacías de Marketplace, merchants y branches, bigint interno/ULID público, timestamps UTC, versión >=1, status exclusivamente draft. Tax_id/risk_level solo NULL, nombres requeridos; Branch tiene FK restrict a su Merchant/Market, timezone explícito y geography(Point,4326) válida/no vacía/2D con GIST. Sin herencia ni unicidad de nombres inventadas.
- **Datos y límites:** migración aditiva/forward-only, aplicación local vacía después de pruebas aisladas; seis tablas Marketplace vacías, sin seeds, usuarios/roles/grants ni nuevos datos en desarrollo. Casos positivos solo en bases temporales propias. Sin API, onboarding, edición, activación, membresías, permisos, contratos, horarios, geocodificación, catálogo, stock o operación real.
- **Bloqueo resuelto:** el usuario respondió «Apruebo» al plan 02G de base vacía. Autorizó implementación, pruebas y aplicación local de la migración vacía con todos sus límites; la publicación quedó reservada al cierre.
- **Plan revisable:** [Checkpoint 02G](../../sprints/sprint-02/checkpoint-02g-plan.md), con campos/checks/índices exactos, relación multi-merchant/market, restricciones provisionales y pruebas.
- **Seguimiento:** después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Si autorizo» el 10 de octubre de 2026; véase el [registro de aprobación](../../sprints/sprint-02/checkpoint-02g-evidence.md#aprobación-y-publicación-autorizadas). DP-001 y las decisiones productivas permanecen abiertas.

## DP-033 — Diagnóstico local de contexto comercial

- **Estado:** resuelta por aprobación explícita del usuario mediante «Apruebo implementarlo» el 10 de octubre de 2026; detectada al continuar después de 02G.
- **Fuente:** maestro v1.6 §§3.1, 9, 32–33, 36, 52–56, 60, 72 y 78–79; DP-032 y ADR-007 aprobaron merchants/branches vacías, sin interfaz de consulta.
- **Vacío original:** no se había autorizado una lectura diagnóstica de las relaciones privadas comercio/sucursal/mercado ni su salida/acceso. No se puede convertir una relación draft en sucursal operativa, permiso de usuario o elegibilidad comercial.
- **Propuesta aprobada:** [plan 02H](../../sprints/sprint-02/checkpoint-02h-plan.md): consola local/testing de solo lectura con tres ULID explícitos (merchant, market, branch), coincidencia exacta de las FK y estado draft de las tres entidades en una sentencia. Salida únicamente versión técnica y matched/not_found, sin nombres, ubicación, datos fiscales ni identificadores internos. Desconocidos y relaciones cruzadas indistinguibles.
- **Datos y límites:** sin API, escrituras, migraciones, seeds, usuarios/permisos, membresías, activación o contratos entre módulos. Desarrollo conserva las seis tablas Marketplace vacías; fixtures positivos solo en bases temporales propias. No cambia las sondas geográficas ni creación fija 02F.
- **Bloqueo resuelto:** el usuario aprobó implementar el plan 02H, incluido el alcance de lectura/acceso/salida. Permite implementar y validar; no autoriza publicación.
- **Seguimiento:** la decisión no modifica responsables/fechas de DP-001 ni resuelve decisiones productivas. Después de presentar el resultado terminado, el usuario autorizó commit/push/CI mediante «Autorizo» el 10 de octubre de 2026; véase el [registro de aprobación](../../sprints/sprint-02/checkpoint-02h-evidence.md#aprobación-y-publicación-autorizadas).

## DP-034 — Alta y consulta local de comercio con primera sucursal

- **Estado:** resuelta por aprobación explícita del usuario mediante «si apruebo» el 10 de octubre de 2026; detectada al continuar tras 02H.
- **Fuente:** maestro v1.6 §§3.1, 9, 25, 32–36, 49–50, 55–60, 72 y 78–79; ADR-002/ADR-007 y DP-026/DP-032/DP-033. La base comercial aprobada solo autoriza persistencia vacía y diagnóstico de lectura.
- **Vacío original:** no estaban concretados acceso de alta/consulta, pertenencia del resultado, forma de entrada, idempotencia/cifrado de auditoría ni mutación comercial. Administración sensible real requiere MFA y no puede anticiparse con el acceso técnico local.
- **Propuesta aprobada:** [plan 02I](../../sprints/sprint-02/checkpoint-02i-plan.md): API local/testing de alta atómica de merchant+primera branch draft en mercado draft existente y consulta del snapshot por actor creador; nombres/punto explícitos y timezone Etc/UTC ficticia. Capacidad create/read independiente, sesión/CSRF y scope/recurso técnicos de servidor. Idempotencia 24h mediante puerto Platform, rollback/concurrencia y diario Marketplace append-only con snapshot cifrado.
- **Datos y límites:** una nueva tabla de auditoría vacía/forward-only, con aplicación local aditiva posterior a pruebas aisladas; desarrollo mantiene siete tablas Marketplace vacías, sin seeds, usuarios/grants ni datos positivos nuevos. Datos ficticios únicamente en bases temporales propias. No frontend/dependencias, MFA administrativa real, activación, fiscalidad/riesgo, catálogo, membresías, contratos, horarios ni piloto real.
- **Bloqueo resuelto:** el usuario aprobó políticas y plan completos de 02I antes de implementar. Autoriza implementación, validación y aplicación local de una tabla vacía.
- **Seguimiento:** no cambia responsables/fechas ni resuelve DP-001/DP-012 o decisiones productivas. Después de presentar la evidencia terminada, el usuario autorizó commit/push/CI mediante «si autorizo» el 10 de octubre de 2026; véase el [registro de aprobación](../../sprints/sprint-02/checkpoint-02i-evidence.md#aprobación-y-publicación-autorizadas).
