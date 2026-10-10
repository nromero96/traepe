# Checkpoint 02C — Base geográfica vacía de Marketplace

Fecha: 10 de octubre de 2026. Continuación de 02B aprobado/publicado en `b4b81f738790a796cfc0b27e4752c80b849f13a2`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38075664329). Origen: maestro v1.6 §§32–34, 54–57 y 64; ADR-002; AGENTS.md.

## Decisión y alcance

DP-028 registró las ambigüedades de propiedad, estados y tipos antes de implementar. El usuario respondió «Aprobar plan 02C (recomendado)» a crear countries, markets y service_zones vacías, propiedad de Marketplace, mercados/zonas solo draft, zone_type solo fixture, PostGIS SRID 4326, sin seeds ni activación real. [ADR-006](../../architecture/decisions/ADR-006-marketplace-geographic-foundation.md) registra la decisión; el [plan aprobado](checkpoint-02c-plan.md) detalla campos, integridad y límites.

La migración agrega únicamente estas tres tablas. Todas usan bigint interno, ULID público válido/único e indexado y timestamps requeridos UTC. País exige código único de dos letras mayúsculas y moneda de tres; mercado tiene país con FK restrict, timezone y moneda explícitos, status=draft y version>=1; zona tiene mercado con FK restrict, zone_type=fixture, prioridad entera con signo, status=draft y version>=1. Nombres/timezone no vacíos; índices en las FK. No se inventan unicidad de nombres, exclusión de solapamientos ni herencia de moneda.

Polygon usa geography(MultiPolygon,4326), requerido/válido/no vacío/2D, con índice GIST. Se comprueba topología sin NOTICE espacial; no se importan coordenadas externas ni se reparan geometrías. La migración es aditiva/transaccional y forward-only, coherente con las migraciones de datos anteriores. Down rechaza un rollback automático; retirar datos requiere un plan revisado.

Las tres tablas están vacías en desarrollo. Los datos sintéticos persistidos por las pruebas viven únicamente en bases temporales propias. Las sondas HTTP, consola y Application conservan los tres polígonos inline y local-coverage-v1; no consultan estas tablas ni hacen elegibles los borradores.

## Estado y validaciones

02C está implementado, aplicado, validado localmente y aprobado por el usuario. Commit, push y CI están autorizados mediante la aprobación final registrada abajo. Al preparar la revisión, HEAD y origin/main permanecían en `b4b81f738790a796cfc0b27e4752c80b849f13a2`, antes de publicar 02C.

| Comando o comprobación | Resultado |
|---|---|
| `docker compose --env-file .env.docker exec -T -e TRAEPE_INTEGRATION_TESTS=1 api vendor/bin/pest --ci tests/Integration/GeographicFoundationTest.php` | 7 pruebas, 176 aserciones en PostgreSQL/PostGIS real; integridad, geometría, aislamiento y rollback protegido correctos |
| `docker compose --env-file .env.docker exec -T api composer quality` | Pint correcto en 155 archivos; PHPStan nivel 8 sin errores; OpenAPI válido y documento malformado rechazado; 83 pruebas Unit/Feature, 1973 aserciones |
| `docker compose --env-file .env.docker exec -T api composer test:integration` | 40 pruebas, 793 aserciones; regresiones de Identity/Platform y cobertura correctas |
| Inventario PostgreSQL antes/después mediante PHP bootstrap, sin filas ni datos sensibles en salida | Tres tablas nuevas con conteo 0; conteos de todas las tablas preexistentes conservados excepto migrations, que aumenta en un registro; sin bases temporales restantes |
| `docker compose --env-file .env.docker exec -T api php artisan migrate --force --no-interaction` | Migración nueva aplicada una vez; segunda ejecución: Nothing to migrate |
| `docker compose --env-file .env.docker exec -T api php tests/Support/verify-foundation.php` | Salud HTTP real, correlación, acceso técnico protegido, sonda Nginx/PHP-FPM/PostGIS y tres tablas vacías correctos; logs técnicos excluyen credenciales y no quedan bases de integración |
| Salud Docker | Ocho servicios saludables: api, nginx, postgres, redis, minio, mailpit, horizon y reverb |
| Revisión de diff, `git diff --check`, LF, enlaces y escaneo de secretos | 16 archivos del checkpoint, sin cambios ajenos al alcance ni secretos |

Total sin contar repeticiones: **123 pruebas y 2766 aserciones**. Se agregan siete pruebas de integración. Cada caso crea su propia base, aplica todas las migraciones y elimina solo esa base al terminar; no hay dependencia entre casos ni reset de desarrollo.

## Pruebas de aceptación

La base limpia presenta las tablas vacías, tipos nativos, campos requeridos e índices esperados. Se prueba segunda ejecución idempotente. Fixtures válidos demuestran UTC, defaults draft/fixture/version, prioridad negativa admitida y moneda explícita diferente a la del país; nombres/polígonos repetidos se conservan sin reglas nuevas.

Se rechazan ULID inválidos/duplicados, código de país duplicado o formato incorrecto, campos vacíos/nulos, FK desconocidas, borrados de país/mercado referenciados, estados activos, tipo de zona operativo y versiones no positivas. PostGIS rechaza geometría inválida, vacía, tipo incorrecto, SRID distinto y dimensiones Z/M. Se verifica el rechazo de down sin eliminar registros existentes.

Una zona draft persistida que cubre el punto sintético (10,10) no cambia outside de las sondas. Se mantienen selected para fixture B y ambiguous para empate. HTTP, Application y consola ejecutan nueve consultas de fixtures; ninguna consulta countries, markets o service_zones ni modifica sus registros.

## Archivos modificados

16 archivos del checkpoint:

- Nueva migración `apps/api/database/migrations/2026_10_10_000003_create_marketplace_geographic_foundation.php`.
- Nueva `apps/api/tests/Integration/GeographicFoundationTest.php`; LocalCoveragePostgisTest y LocalCoverageHttpPostgisTest ahora verifican las tres tablas vacías y ausencia de comercios/sucursales.
- `apps/api/tests/Support/verify-foundation.php` verifica existencia vacía de las tablas aprobadas y ausencia de tablas operativas.
- Once Markdown: README de API, índice documental, arquitectura, modelo de datos, índice ADR, registro DP-028, ADR-006 nuevo, especificación Marketplace, README Sprint 02, plan aprobado y esta evidencia.

## Límites

DP-001 permanece abierta para el piloto real. Estados/tipos operativos y activación requerirán decisiones nuevas y constraints versionables; las mutaciones necesitarán autorización, concurrencia, auditoría/historial y eventos cuando corresponda. No hay administración, importación, seeds, roles/grants, usuarios nuevos, comercios, sucursales, direcciones, zone_rules, resolución operativa de mercado, tarifas, horarios, ETA ni frontend.

No se agregan dependencias ni se modifica el contrato HTTP/OpenAPI. Los datos y volúmenes de desarrollo se conservan; la comprobación local comparó conteos, sin extraer contenido de tablas.

## Aprobación y publicación autorizadas

El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la pregunta «¿Apruebas 02C y autorizas commit, push y CI?», después de presentar la implementación y su evidencia local. Esta respuesta aprueba el checkpoint y autoriza publicar exclusivamente sus 16 archivos revisados, conservando las tablas vacías, las restricciones draft/fixture y DP-001 abierta.

La publicación se realiza desde main mediante commit y push normales. La ejecución de GitHub Actions debe corresponder al SHA publicado y completar calidad, integración, reinicio/persistencia y limpieza. El resultado remoto se informa al cerrar la publicación.
