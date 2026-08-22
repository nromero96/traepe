# Definición de terminado — Sprint 00

## Para cada tarea

Una tarea está `DONE` cuando:

- su decisión bloqueante está aprobada y documentada;
- objetivo, trabajo y archivos esperados coinciden con el resultado;
- cumple todos sus criterios de aceptación;
- sus dependencias están `DONE`;
- incluye y supera las pruebas indicadas, con casos positivos, negativos y límites relevantes;
- respeta arquitectura modular, seguridad, privacidad y alcance negativo;
- no introduce secretos, PII en logs, dependencias innecesarias ni reglas no aprobadas;
- actualiza documentación, contrato o ADR aplicable;
- registra riesgos residuales y no oculta fallas mediante baselines o exclusiones injustificadas;
- informa archivos modificados y comandos de validación ejecutados.
- pertenece a un checkpoint cuyas pruebas fueron ejecutadas, cuyo diff fue revisado y cuyo resumen de archivos/riesgos recibió aprobación antes de continuar.

## Para código e infraestructura

- Dependencias y versiones quedan en lockfiles/manifests revisables.
- Pint y Larastan/PHPStan pasan sin deuda nueva oculta.
- Pest y pruebas de integración pasan sin depender de orden de ejecución.
- Migraciones incluyen constraints/índices necesarios, son reproducibles y forward-compatible.
- Servicios tienen healthchecks y configuración por entorno.
- Operaciones de reset/destrucción local son explícitas, acotadas y documentadas.
- Logs y respuestas no filtran trazas, secretos, credenciales ni PII.

## Para el Sprint 00

El sprint está terminado únicamente cuando:

- S00-001 a S00-016 están `DONE` en orden compatible con sus dependencias;
- 00A, 00B, 00C, 00D, 00E y 00F tienen evidencia de pruebas, revisión de diff, archivos, riesgos y aprobación secuencial;
- se cumplen íntegramente los [criterios de aceptación](acceptance-criteria.md);
- Docker Compose y GitHub Actions reproducen los mismos controles esenciales;
- una instalación limpia ha sido ejecutada por una persona distinta del autor o revisada independientemente;
- no se implementó catálogo, pedidos, pagos, logística, pricing, dashboards ni negocio;
- las decisiones abiertas restantes no bloquean el siguiente sprint y tienen responsable/fecha;
- el checkpoint final recibe aprobación explícita.

Completar documentación sin una fundación ejecutable no satisface esta definición.
