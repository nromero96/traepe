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
