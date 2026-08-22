# ADR-004 — Calidad y CI

- **Estado:** Aprobado
- **Fecha:** 22 de agosto de 2026
- **Decisión:** Pest, Laravel Pint, Larastan/PHPStan y GitHub Actions.

## Consecuencias

- Pest cubre pruebas unitarias, feature, contract e integration según riesgo.
- Pint es la puerta de formato.
- Larastan/PHPStan aplica análisis estático sin ocultar deuda nueva.
- GitHub Actions ejecuta controles con permisos mínimos.
- CD, hosting y despliegue no quedan decididos por este ADR.
