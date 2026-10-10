# Checkpoint 02A — Selección de cobertura ficticia local

Fecha: 10 de octubre de 2026. Origen: maestro v1.6 §§4.1, 32–34.1 y 64; módulo Marketplace aprobado en la arquitectura. El usuario eligió «Cobertura ficticia local (recomendado)» y aprobó los criterios de DP-027. DP-001 sigue pendiente para el piloto operativo.

## Resultado y trazabilidad

Marketplace tiene cuatro capas utilizadas. Domain incluye GeographicPoint, ZoneCandidate, ZoneSelection y LocalZoneSelectionPolicy, sin dependencias de Laravel/PostGIS. Application define LocalZoneSource y LocalCoverageProbe. Infrastructure implementa la lectura con PostGIS y el provider. Interfaces expone únicamente marketplace:local-coverage por consola.

El punto valida números finitos y rangos WGS84; conserva longitud/latitud explícitas. La fuente ejecuta una consulta parametrizada sobre tres polígonos sintéticos en una CTE, con SRID 4326 y predicado inclusivo. No accede a tablas de Identity ni consulta/escribe mercados, zonas, comercios o sucursales. La política selecciona mayor prioridad, devuelve outside sin candidatos y ambiguous sin zona al empatar máximos distintos, independientemente del orden.

Los polígonos están junto al origen matemático y contienen solapamientos/hueco. Las referencias públicas son ULID fijos de fixture. No representan un distrito ni una cobertura de lanzamiento. El resultado incluye fixture_version=local-coverage-v1, status y zone_id opcional; no es disponibilidad operativa ni promesa de entrega.

El provider registra puerto/comando solo local/testing; comando y adaptador conservan un gate de ejecución. El comando verifica entorno antes de resolver servicios, valida formato/rango y devuelve fallos genéricos, sin coordenadas, SQL ni detalles internos. No se agrega ninguna ruta HTTP o capacidad de Identity.

## Validación

Validaciones ejecutadas el 10 de octubre de 2026 en el entorno Docker existente:

| Validación | Resultado |
|---|---|
| `docker compose --env-file .env.docker exec -T api composer quality` | Pint correcto en 148 archivos; PHPStan nivel 8 sin errores; OpenAPI válido y contrato malformado rechazado; Unit/Feature: 67 pruebas, 1505 aserciones |
| `docker compose --env-file .env.docker exec -T api composer test:integration` | 32 pruebas, 565 aserciones; regresiones PostgreSQL, Identity/Platform y cobertura PostGIS correctas |
| PostgreSQL/PostGIS de 02A, repetido tras el ajuste de tipo | 4 pruebas, 61 aserciones correctas |
| `docker compose --env-file .env.docker exec -T api php tests/Support/verify-foundation.php` | Fundación correcta: salud HTTP, correlación, errores sanitizados, módulos aprobados, ausencia de tablas comerciales y de bases temporales, secretos excluidos |
| Comando local con punto sintético `3.5 0.5` | `selected`, fixture B y versión `local-coverage-v1`; sin escrituras |
| `docker compose --env-file .env.docker ps` | api, nginx, postgres, redis, minio, mailpit, horizon y reverb saludables |

Total sin contar repeticiones: **99 pruebas y 2070 aserciones**; 21 pruebas nuevas de 02A. PHPStan señaló inicialmente que el retorno del adaptador debía cumplir `list<ZoneCandidate>`; se normalizaron sus índices con `array_values`, pasó la suite de calidad completa y se repitieron las cuatro pruebas de PostGIS. No se modificaron las reglas de selección.

La revisión incluye límites de dependencias, diff sin errores de espacios, finales de línea LF, enlaces locales y escaneo de los 24 archivos del checkpoint contra patrones de credenciales y secretos del entorno ignorado. No se agregan secretos ni cambios ajenos al alcance. Al preparar la revisión, HEAD y origin/main conservaban `38684843baf5fafbd2ef53d3f2bd9665ed106927`, antes de publicar 02A.

## Operación local

Desde la raíz, con Docker y las migraciones existentes aplicadas:

```powershell
docker compose --env-file .env.docker exec -T api php artisan marketplace:local-coverage 3.5 0.5 --no-interaction
docker compose --env-file .env.docker exec -T api php artisan marketplace:local-coverage 10 10 --no-interaction
docker compose --env-file .env.docker exec -T api php artisan marketplace:local-coverage 5.5 1.5 --no-interaction
```

Resultados respectivos: selected para el fixture B, outside y ambiguous. Los tres son evaluaciones técnicas exitosas; ambiguous/outside llevan zone_id=null. Para valores negativos de consola usar el separador de opciones `--` antes de las coordenadas. Usar únicamente puntos del ejercicio; no introducir direcciones/personas ni zonas operativas.

## Cambios y límites

El checkpoint comprende 24 archivos:

- Nueve nuevos en `apps/api/app/Modules/Marketplace`: cuatro de Domain/Coverage, dos de Application/Coverage, adaptador Infrastructure/Coverage, MarketplaceServiceProvider y comando Interfaces/Console.
- `apps/api/bootstrap/providers.php` registra el provider.
- Tres pruebas nuevas: `tests/Unit/LocalCoveragePolicyTest.php`, `tests/Feature/LocalCoverageCommandTest.php` y `tests/Integration/LocalCoveragePostgisTest.php`.
- Tres checks actualizados: ArchitectureTest, ModuleScaffoldingTest y `tests/Support/verify-foundation.php` admiten exactamente Identity, Marketplace y Platform.
- Ocho documentos: `apps/api/README.md`, `docs/README.md`, `docs/architecture/README.md`, `docs/architecture/decisions/pending-decisions.md`, `docs/specifications/marketplace-and-purchase.md`, `docs/sprints/sprint-01/README.md` y los dos documentos de Sprint 02.

Sin migraciones, seeds, dependencias, lockfiles, privilegios, rutas ni proveedores externos nuevos. Polígonos válidos, 2D y SRID se comprueban en PostGIS real. Las bases de integración pertenecen a cada test y se eliminan; se conservan los volúmenes y datos de desarrollo.

Este ejercicio no define tablas markets/service_zones, estados comerciales, zone_rules, horarios, tarifas, ETA, geocodificación, disponibilidad por sucursal ni contratos de checkout. Antes de operar se requiere DP-001 y las reglas/matriz del módulo dueño.

## Aprobación y publicación autorizadas

El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la pregunta: «¿Apruebas 02A y autorizas commit, push y validación en GitHub Actions?». Esta respuesta aprueba el checkpoint y autoriza publicar los 24 archivos revisados, sin ampliar su alcance ficticio ni resolver DP-001.

La publicación se realiza desde main mediante commit y push normales. La comprobación remota debe corresponder al SHA publicado y finalizar correctamente, incluidos reinicio/persistencia y limpieza de recursos de CI. El resultado de esa ejecución se informa al cerrar la publicación.

## Referencias técnicas verificadas

[ST_Covers 3.5](https://postgis.net/docs/manual-3.5/ST_Covers.html) incluye interior/borde y exige geometrías válidas. [ST_MakePoint 3.5](https://postgis.net/docs/manual-3.5/ST_MakePoint.html) define X=longitud/Y=latitud y SRID explícito. Las funciones respaldan la ejecución técnica de DP-027; no resuelven decisiones operativas.
