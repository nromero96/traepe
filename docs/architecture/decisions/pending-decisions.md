# Decisiones pendientes

Las decisiones técnicas aprobadas para Sprint 00 se trasladaron a ADR. Este registro conserva únicamente asuntos todavía abiertos.

## Negocio pendiente

| ID | Decisión | Contexto | Bloquea |
|---|---|---|---|
| DP-001 | Mercado piloto | Distritos y horarios de lanzamiento | Markets/zones |
| DP-002 | Modelo inicial de cobro | Tarifa cliente, comisión y subsidios | Pricing/Settlements |
| DP-003 | Modelo de flota | Propia, terceros, independientes o mixto | Logistics |
| DP-004 | Pago y proveedor inicial | Pasarela, Yape/Plin, efectivo/contraentrega | Payments |
| DP-005 | Facturación | Emisor y separación de cargos | Payments/Settlements |
| DP-006 | Política de cancelación | Ventanas, responsables y costos | Ordering/Payments |
| DP-007 | Verificación de edad | Procedimiento y rechazo | Ordering/Logistics |
| DP-008 | Valores de SLA | Aceptación, preparación, búsqueda y entrega | Platform/Logistics |
| DP-009 | Modelo de soporte | Canales, horarios, escalamiento y compensaciones | Support |
| DP-010 | Proveedores externos productivos | Mapas, mensajería, storage, identidad, correo e invoicing | Adaptadores productivos |

## Técnica pendiente fuera de Sprint 00

| ID | Decisión | Contexto | Bloquea |
|---|---|---|---|
| DP-012 | Herramientas concretas de frontend | Frontend continúa fuera de Sprint 00 | Apps web futuras |
| DP-013B | CD y hosting | Proveedor, ambientes, secretos, despliegue y rollback | Despliegue futuro |
| DP-022 | RTO/RPO iniciales | Requiere objetivos de negocio y capacidad operativa | Recuperación productiva |

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
