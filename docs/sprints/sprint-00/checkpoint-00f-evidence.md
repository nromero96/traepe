# Checkpoint 00F — Calidad, CI y reproducción

Fecha: 3 de octubre de 2026. El usuario aprobó 00E y autorizó 00F. Alcance: S00-014, S00-015 y S00-016; documento maestro v1.6 §§51–64 y 112, ADR-001/004 y criterios del Sprint 00. La implementación está preparada y validada localmente. El cierre final permanece pendiente de ejecución remota de GitHub Actions, revisión independiente y aprobación explícita.

No se hizo commit, push, merge, publicación ni despliegue. No se implementaron dominios comerciales ni flujos de identidad. HEAD y origin/main siguen en `a96ac04400bfe9d8d24ff142e537059dca813c48`.

## Resultado

- Pest ejecuta Unit/Feature e Integration, conservando clases PHPUnit y usando API nativa en las dos pruebas existentes de fingerprint.
- Pint tiene preset Laravel explícito. PHPStan/Larastan usa nivel 8 y PHP 8.4 sobre `app`, `bootstrap`, `config`, `database` y `routes`; no tiene baseline, exclusiones, ignores ni supresiones.
- Los 26 hallazgos iniciales se resolvieron: PHPDocs de colecciones, claim nullable, tipos de adaptador S3 y logger/handlers, conversión explícita de variables de entorno y expresión redundante. No se cambió una regla de negocio. Un estado inconsistente del claim/logger falla de forma cerrada.
- Composer incorpora `test`, `test:integration`, `format`, `format:check`, `analyse`, `contract:check` y `quality`, con códigos de salida fiables. Tres errores controlados comprueban exit 1 para test, formato y análisis; sus fixtures se eliminan en finally.
- Workflow PR/push main/dispatch, Ubuntu 24.04, timeout 30 minutos, permisos `contents: read`, sin credenciales persistidas ni secretos externos. Acciones checkout/cache fijadas por SHA. Solo se conserva caché de descargas Composer; vendor se verifica desde lock.
- El mismo runner PowerShell usado por Actions crea un stack CI independiente sin puertos publicados, ejecuta calidad e infraestructura y reinicia para verificar persistencia. Sus redes y volúmenes son `traepe-ci-*`. La limpieza revisa proyecto, etiquetas y prefijo; remueve solo sus volúmenes temporales individualmente. Nunca ejecuta `down -v` ni toca volúmenes del desarrollo.
- El inicializador local genera APP_KEY compartida y credenciales independientes en archivos ignorados; rechaza sobrescritura. El runbook permite repetir migraciones/reiniciar conservando datos y no introduce resets destructivos del desarrollo.

## Dependencias

| Paquete dev | Versión fijada |
|---|---|
| pestphp/pest | 4.7.8 |
| pestphp/pest-plugin-laravel | 4.1.0 |
| larastan/larastan | 3.12.2 |
| phpstan/phpstan | 2.2.16 |
| laravel/pint existente | 1.30.5 |
| phpunit/phpunit existente | 12.5.33 |

Composer agregó 21 paquetes dev/transitivos, sin actualizar ni remover paquetes existentes. Se instaló dentro de Docker, con acceso de red temporal y desconexión en finally. El lock fija todos los paquetes. Composer validate estricto y audit no detectan errores ni avisos.

## Validación ejecutada

Se creó `.tmp/00f-clean` copiando los 187 archivos versionados/propuestos del working tree, sin `.git`, `.env`, vendor ni caches ignoradas. Es una reproducción de la propuesta completa; no se presenta como un checkout de un commit ya publicado. Bases, volúmenes y vendor empezaron vacíos. Los layers de imagen base y MinIO pudieron usar caché Docker; Composer instaló el nuevo lock desde la imagen.

Proyecto descartable: `traepe-ci-00f-review`. Se ejecutaron los stages `Prepare`, `Build`, `Check`, `Restart` y `Cleanup` del mismo runner que invoca el workflow. La reproducción local es evidencia del runner y del stack, no de la ejecución del servicio GitHub Actions.

| Control | Resultado local |
|---|---|
| Instalación lock sobre vendor nuevo | Correcta; ningún cambio de resolución |
| Composer validate estricto / audit | Correcto / sin avisos |
| Migraciones sobre base nueva | Cinco migraciones correctas |
| Migraciones sobre base temporal / segunda ejecución | Correctas / idempotente |
| Pest Unit/Feature | 26 pruebas, 754 assertions |
| Pest PostgreSQL Integration | 7 pruebas, 67 assertions |
| Total | 33 pruebas, 821 assertions |
| Imagen final sin mounts ni red, composer quality | 26 pruebas/754 assertions, Pint y PHPStan correctos |
| Pint | 98 archivos correctos |
| PHPStan/Larastan nivel 8 | Sin errores |
| Sintaxis PHP | 99 archivos correctos |
| Fallas controladas Pest/Pint/PHPStan | Cada puerta devuelve exit 1 |
| OpenAPI oficial / contrato inválido | Valida / rechazo esperado |
| Esquema v1 del evento cifrado persistido | Valida; entrada inválida rechazada |
| PostGIS / Redis caché | Correctos |
| S3 write/read/delete, acceso anónimo y credencial restringida | Correctos / denegaciones esperadas |
| Mailpit SMTP | Mensaje ficticio recibido |
| Horizon procesamiento, redelivery y retry | Correctos |
| Fallo terminal, observable en Horizon, retry explícito | Correctos |
| Reverb handshake, autorización privada, firma inválida, evento y reconexión | Correctos |
| Productor/outbox/inbox real por Horizon | Replay estable, duplicado con un único efecto |
| HTTP real, liveness/readiness/error/Horizon anónimo | Correctos / rechazo esperado |
| Correlación de 16 HTTP concurrentes y privacidad Nginx | Correctas |
| Alcance negativo | Solo Platform; sin tablas comerciales; users del starter Laravel vacía |
| Credenciales locales/CI en logs y fuentes propuestas | No encontradas |
| Inicializador y rechazo de sobrescritura | Correctos; no imprime valores |
| Proyecto CI preexistente | Preparación rechazada sin tocar sus recursos |
| Reinicio y persistencia S3/Redis/PostgreSQL/Mailpit | Correctos; probe eliminado |
| Limpieza CI | Solo recursos temporales propios; volúmenes de desarrollo conservados |
| Compose local/CI, sintaxis PowerShell y YAML del workflow | Correctos; YAML leído por parser Docker, sin validación remota de Actions |
| Git diff --check y staging | Correcto; staging vacío |

La tabla `users` y otras tablas base del starter provienen de 00A; permanecen sin usuarios y no implican implementación de Identity. No se eliminaron estructuras existentes para aparentar cumplimiento del alcance negativo.

## Archivos del bloque 00F

- `apps/api/composer.json`, `composer.lock`, `pint.json`, `phpstan.neon`, `tests/Pest.php`, `tests/Unit/RequestFingerprintTest.php`: dependencias, configuración y ejecución de calidad.
- `apps/api/tests/Support/verify-quality-gates.php`, `verify-foundation.php`: fallas controladas, HTTP real, alcance negativo y privacidad.
- `apps/api/app/Events/TechnicalRealtimeProbe.php`; puertos/caso de Delivery, Readiness y tipos de Domain; `PostgresIdempotencyStore`, jobs de entrega, `DependencyHealth`, `ApiResponse` y `ConfigureSafeLogging`; `config/filesystems.php`, `horizon.php`, `sanctum.php`: tipos explícitos y correcciones exigidas por análisis.
- `.github/workflows/ci.yml`, `compose.ci.yaml`, `infrastructure/ci/Invoke-CI.ps1`: pipeline y reproducción aislada.
- `infrastructure/docker/Initialize-LocalEnvironment.ps1`, `compose.yaml`: arranque seguro e imagen de 00F.
- `.gitignore`, `.dockerignore`, `.gitattributes`: exclusión de entornos/caches temporales y política LF para PowerShell/NEON.
- README raíz/API, `docs/README.md`, `docs/installation/{README,docker,environment}.md`, ADR-004, Sprint 00 README/backlog y evidencia: comandos, trazabilidad y estado honesto del cierre.
- La evidencia de 00E registra su aprobación. Cambios previos de 00C/00D/00E siguen presentes sin commit.

Imagen PHP: `traepe/php-fpm:8.4.24-00f`, digest `sha256:5705d0865ef3117be669a964f45982a92b186c434c9ae3a4e5b62ca0547311d9`. PHP 8.4.24, Composer 2.8.12 y Laravel 13.34.0. Las imágenes de terceros conservan sus versiones/digests en Compose. MinIO conserva commits aprobados de DP-024.

## Pendientes para cierre

| Tarea | Estado |
|---|---|
| S00-014 | Implementación y validación completas; revisión de checkpoint pendiente |
| S00-015 | Workflow/runner completos y reproducción local verde; falta ejecución remota desde commit publicado |
| S00-016 | Runbook, instalación nueva y reinicio verificados; falta revisión independiente y aprobación final |

No se declara terminado Sprint 00 mientras estas puertas sigan pendientes. Publicar los cambios requiere autorización explícita conforme a AGENTS.md; aprobar 00E/iniciar 00F no constituye autorización de commit/push.

Los riesgos productivos existentes permanecen: MinIO local con upstream archivado, proveedores/hosting/CD, políticas de retención/rotación y calendario de publicación, RTO/RPO, decisiones de negocio y frontend. No se resolvieron silenciosamente ni se implementaron funcionalidades del siguiente sprint. Docker Desktop requiere recursos suficientes para dos stacks simultáneos durante una reproducción aislada.

Referencias: [guía ejecutable](../../installation/README.md), [ADR-004](../../architecture/decisions/ADR-004-quality-ci.md), [decisiones pendientes](../../architecture/decisions/pending-decisions.md).

## Autorización de publicación — 3 de octubre de 2026

Después de revisar este informe, el usuario autorizó explícitamente crear el commit acumulado de 00C–00F, publicar en main y verificar GitHub Actions. La validación remota se registrará con su URL y SHA verificados; no se declarará exitosa antes de que el run concluya.
