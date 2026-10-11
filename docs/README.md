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

Checkpoint publicado más reciente: [03D](sprints/sprint-03/checkpoint-03d-evidence.md#publicación-confirmada), main 268bae47233697f659be7600a4335f5bff702c2c. [Foundation CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38161448476), run 38161448476/job 114535038415: SHA exactos y cuatro etapas correctas. Alta/colección/historial de variantes draft con permisos independientes, idempotencia/concurrencia y diario cifrado. 51 archivos, 355 pruebas/11260 aserciones locales y remotas, Pint 322 con caché nueva/PHPStan 8/contratos correctos. Dos tablas vacías aplicadas y 35 previas preservadas; 37 tablas no-migrations, catorce Marketplace/Catalog vacías y ocho servicios saludables. DP-038 aprobada mediante «si apruebo»; publicación autorizada mediante «Autorizado y continuar».

El usuario pidió continuar: [plan completo 03E](sprints/sprint-03/checkpoint-03e-plan.md) implementado para edición/historial de variantes y [DP-039 aprobada](architecture/decisions/pending-decisions.md#dp-039--edición-local-de-variantes-draft). Incluye cinco campos editables/SKU inmutable local, tres revisiones, dos permisos independientes, colección actual v2 y diario nuevo vacío con CHECK de revisión ampliado; [evidencia](sprints/sprint-03/checkpoint-03e-evidence.md). Plan conjunto aprobado mediante «Si apruebo»; implementación validada localmente: 380 pruebas/12982 aserciones, Pint 343/PHPStan 8/contratos correctos, 37 tablas previas preservadas, quince Marketplace/Catalog vacías y ocho servicios saludables. Commit/push/CI de 03E autorizados mediante «si autorizo» el 11 de octubre de 2026; [publicación funcional confirmada](sprints/sprint-03/checkpoint-03e-evidence.md#publicación-confirmada) en main b0be84e1ccb126059a9fa551a3a98a370547465e, Foundation CI 38170842832/job 114562936788 y cuatro etapas correctas. Solo ficticio local/testing, sin precio/stock/listing, venta/activación, datos reales o frontend/dependencias nuevas. Decisiones productivas conservan responsables/fechas y siguen abiertas. Sprint 00 conserva su cierre aprobado en [00F](sprints/sprint-00/checkpoint-00f-evidence.md).
