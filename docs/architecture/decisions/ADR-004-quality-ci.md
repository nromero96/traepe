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

## Concreción de 00F — 3 de octubre de 2026

El usuario aprobó 00E y autorizó 00F. Se agregaron únicamente dependencias de desarrollo compatibles, sin actualizar paquetes existentes: Pest 4.7.8, plugin Laravel 4.1.0, Larastan 3.12.2 y PHPStan 2.2.16; sus dependencias necesarias quedan fijadas en Composer lock. Pint existente permanece en 1.30.5. Pest ejecuta también las clases PHPUnit existentes; dos pruebas de fingerprint usan su API nativa.

PHPStan aplica nivel 8 a `app`, `bootstrap`, `config`, `database` y `routes`, incluido Platform y Shared. No hay baseline, ignores ni exclusiones. Los tipos incompletos se documentaron y los accesos nullable/adaptadores se verifican explícitamente. No se cambian reglas comerciales. Véase [niveles oficiales](https://phpstan.org/user-guide/rule-levels) y [compatibilidad Larastan](https://github.com/larastan/larastan/blob/3.x/composer.json).

El workflow cubre PR, push a main y dispatch manual, en Ubuntu 24.04, con `contents: read`, credenciales de checkout desactivadas y acciones fijadas por SHA. Referencias verificadas del upstream: [checkout v7.0.1](https://github.com/actions/checkout/releases/tag/v7.0.1) y [cache v6.1.0](https://github.com/actions/cache/releases/tag/v6.1.0). Los mismos stages del runner PowerShell se reproducen localmente mediante Docker; CI no publica puertos ni usa secretos externos. Solo las descargas Composer se conservan en caché.

Redes/volúmenes CI usan identidad `traepe-ci-*` independiente, credenciales efímeras y limpieza limitada a recursos etiquetados/verificados de ese proyecto. Se conserva el entorno de desarrollo y se prohíben resets globales. Una ejecución local desde snapshot limpio del working tree valida el runner; el estado verde de GitHub Actions debe verificarse después de autorizar publicación. Este ADR no autoriza por sí solo commit/push, CD ni despliegue.
