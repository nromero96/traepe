# Documentación de trae.pe

## Fuente oficial

- [Documento Maestro Funcional v1.6](master/Documento_Maestro_Funcional_trae_pe_v1_6.docx)
- Fecha declarada: 22 de agosto de 2026.
- SHA-256 verificado durante la preparación: `85FEF02B0CBA9669EE37AAE4986BD7709634CB93987060435AF0A3759A802C2E`.

El maestro gobierna requisitos funcionales, datos, API, UX y diseño. Esta documentación lo reorganiza sin crear reglas nuevas.

## Índice navegable

1. [Especificaciones funcionales](specifications/README.md)
2. [Reglas de negocio](specifications/business-rules/README.md)
3. [Arquitectura](architecture/README.md)
4. [Modelo de datos](architecture/data-model/README.md)
5. [Seguridad](architecture/security/README.md)
6. [Requisitos no funcionales](architecture/non-functional/README.md)
7. [Decisiones de arquitectura](architecture/decisions/README.md)
8. [Contrato API](api/README.md)
9. [Eventos](events/README.md)
10. [Producto y experiencia](product/README.md)
11. [Preparación técnica completada](sprints/preparation/README.md)
12. [Sprint 00](sprints/sprint-00/README.md)
13. [Entorno base de instalación](installation/environment.md)
14. [Docker Compose local](installation/docker.md)
15. [Evidencia del Checkpoint 00C](sprints/sprint-00/checkpoint-00c-evidence.md)
16. [Evidencia del Checkpoint 00D](sprints/sprint-00/checkpoint-00d-evidence.md)
17. [Evidencia del Checkpoint 00E](sprints/sprint-00/checkpoint-00e-evidence.md)
18. [Instalación y calidad](installation/README.md)
19. [Evidencia del Checkpoint 00F](sprints/sprint-00/checkpoint-00f-evidence.md)
20. [Sprint 01 — Identity backend local](sprints/sprint-01/README.md)
21. [Sprint 02 — Marketplace local](sprints/sprint-02/README.md)
22. [Sprint 03 — Catálogo local](sprints/sprint-03/README.md)

## Reglas de gobernanza

- Cada especificación indica las secciones de origen del maestro.
- El lenguaje `debe`/`no debe` conserva carácter obligatorio.
- Una síntesis no reemplaza las matrices detalladas del DOCX.
- Conflictos o vacíos se anotan como [decisiones pendientes](architecture/decisions/pending-decisions.md).
- Una decisión solo pasa a aprobada mediante actualización explícita del maestro o un ADR aprobado que no contradiga negocio.

## Mapa de trazabilidad

| Área | Fuente v1.6 | Documento navegable |
|---|---:|---|
| Visión, actores y MVP | 1–17 | `specifications/` |
| Estados y operación | 18–30 | `specifications/business-rules/` |
| Datos | 31–50 | `architecture/data-model/` |
| Técnica y API | 51–64 | `architecture/`, `api/`, `events/` |
| Producto y backlog MVP | 65–81 | `product/` |
| Interfaces | 82–96 | `product/interfaces.md` |
| Design system y wireframes | 97–111 | `product/design-system.md` |
| Preparación documental | Derivada de 1–112 | `sprints/preparation/` |
| Sprint 00 ejecutable | 112 | `sprints/sprint-00/` |

Checkpoint publicado más reciente: [03A](sprints/sprint-03/checkpoint-03a-evidence.md#publicación-confirmada), commit main 0690cc0ec63493a163652ec68db52c184f700e26, con [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38109718961), run 38109718961/job 114382575818 sobre ese SHA y cuatro pasos obligatorios correctos. Alta/consulta de catálogo con primer producto conceptual draft, sesión/CSRF, capacidades independientes, referencia Marketplace pública, idempotencia 24h y diario cifrado append-only. 282 pruebas locales/7190 aserciones, tres tablas nuevas vacías y las 30 anteriores preservadas; diez tablas Marketplace/Catalog vacías y ocho servicios saludables. DP-035/ADR-008 aprobados y publicación autorizada mediante «Si autorizo». El usuario solicitó continuar: [plan completo 03B](sprints/sprint-03/checkpoint-03b-plan.md) aprobado mediante «Aprobar plan completo 03B (recomendado)»/DP-036 para productos adicionales y contenido actual; implementado y validado localmente: 307 pruebas/8532 aserciones, 55 archivos, Pint 277/PHPStan 8/contratos correctos y una tabla/índice nuevos vacíos, con las 33 tablas previas preservadas y once tablas Marketplace/Catalog vacías. [Evidencia](sprints/sprint-03/checkpoint-03b-evidence.md). Commit/push/CI de 03B autorizados mediante «Autorizado» el 11 de octubre de 2026; [publicación y verificación remota en curso](sprints/sprint-03/checkpoint-03b-evidence.md#aprobación-y-publicación-autorizadas). Solo ficticio local/testing, sin venta, activación o frontend/dependencias nuevos. Decisiones productivas conservan responsables/fechas y siguen abiertas. Sprint 00 conserva su cierre aprobado en [00F](sprints/sprint-00/checkpoint-00f-evidence.md).
