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

Checkpoint publicado más reciente: [02G](sprints/sprint-02/checkpoint-02g-evidence.md), aprobado en `6d3dbb53bbcb9056120589c260208c0ab67fa79c` y validado en [GitHub Actions](https://github.com/nromero96/traepe/actions/runs/38093771857), incluidos build/migración, calidad/integración, reinicio/persistencia y limpieza. Base privada vacía de merchants/branches, solo draft y tax_id/risk_level únicamente NULL, relaciones propias de Marketplace y geography Point 4326 con GIST. Validación local: 215 pruebas y 4592 aserciones, seis tablas Marketplace vacías y ocho servicios saludables; datos previos preservados. Tras la solicitud de continuar, se preparó el [plan 02H](sprints/sprint-02/checkpoint-02h-plan.md) de diagnóstico de consola de solo lectura de la relación merchant/market/branch, con salida técnica mínima. DP-033 fue aprobada mediante «Apruebo implementarlo»; [02H está implementado y validado localmente](sprints/sprint-02/checkpoint-02h-evidence.md): 228 pruebas y 4820 aserciones, seis tablas vacías y ocho servicios saludables. El usuario autorizó el cierre y commit/push/CI mediante «Autorizo»; véase el registro en la evidencia. DP-001 y las decisiones productivas siguen abiertas. Sprint 00 conserva su cierre aprobado en [00F](sprints/sprint-00/checkpoint-00f-evidence.md).
