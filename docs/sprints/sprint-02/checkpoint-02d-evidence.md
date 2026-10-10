# Checkpoint 02D — Diagnóstico geográfico persistido por mercado

Fecha: 10 de octubre de 2026. Continuación de 02C aprobado/publicado en `720f0fa4c54cb9c7673fc4da26ba21e8b6f3e56a`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38078507497), incluidos reinicio/persistencia y limpieza. Origen: maestro v1.6 §§32–34.1, 55–56 y 64; DP-027/DP-028; ADR-006; AGENTS.md.

## Aprobación del alcance y contrato

Antes de implementar, DP-029 registró la ampliación respecto de las tablas vacías y las sondas inline. El usuario respondió «Aprobar plan 02D (recomendado)» a un diagnóstico de consola local/testing, lectura de zonas draft/fixture del mercado draft indicado por ULID, geography nativa, bordes incluidos, máxima prioridad y rechazo de empates. Sin escrituras, seeds, API ni activación; desarrollo sigue vacío y los fixtures persistidos están solo en bases de prueba. El [plan aprobado](checkpoint-02d-plan.md) conserva la propuesta, aceptación y constatación técnica.

`marketplace:local-persisted-coverage {market_public_id} {longitude} {latitude}` valida ULID y punto WGS84 antes de consultar. Domain contiene MarketPublicId; Application recibe mercado/punto explícitos, define puerto/resultado inmutables y reutiliza LocalZoneSelectionPolicy. Infrastructure consulta únicamente markets/service_zones propias, en una sentencia parametrizada y con filtros de mercado draft, FK de zona, status=draft y zone_type=fixture. No expone bigint ni nombres, país, geometría, coordenadas, SQL o bindings. Command y adaptador deniegan fuera de local/testing antes de resolver/consultar; el provider no registra puerto/comando en producción/staging.

JSON de claves cerradas: fixture_version=local-persisted-coverage-v1, status y zone_id. Estados técnicos: market_not_found para mercado draft ausente; outside para mercado conocido sin coincidencias; selected para máximo único; ambiguous para empate entre zonas distintas de máxima prioridad. Solo selected lleva ULID público de zona; los demás null. Los cuatro son diagnósticos correctos con salida 0. Entrada inválida, entorno denegado o infraestructura fallida devuelven salida 1 y mensaje genérico sin reflejar entradas o detalles.

Selected no autoriza servicio, entrega ni checkout. La selección de 02A/02B sigue usando sus tres polígonos inline, su fuente y versión local-coverage-v1; el contrato HTTP/OpenAPI no cambia.

## Geografía nativa y bordes

La selección no convierte el polígono persistente a geometry. ST_Covers se complementa con ST_DWithin(..., 0) en la misma sentencia, sobre geography. En PostGIS 3.5.7 se reprodujo por lectura que Covers excluye el punto sobre el borde meridiano de un hueco, mientras Within a distancia 0 lo incluye. El complemento cumple el borde incluido aprobado; no agrega buffer ni umbral positivo. Se prueban el borde, ambos lados y el interior del hueco. No se usa ST_Distance redondeada ni ST_Intersects con tolerancia como predicado.

La prueba geodésica también conserva un caso donde geometry plana cubriría el punto y geography nativa lo excluye. Esa diferencia es explícita en DP-029 y no sustituye el diagnóstico plano existente. [ST_Covers](https://postgis.net/docs/manual-3.5/ST_Covers.html), [ST_DWithin](https://postgis.net/docs/manual-3.5/ST_DWithin.html) y [código oficial de PostGIS 3.5.7](https://github.com/postgis/postgis/blob/3.5.7/postgis/geography_measurement.c) respaldan las comprobaciones técnicas documentadas en el plan.

## Estado y validación local

02D está implementado, validado localmente y aprobado por el usuario. Commit, push y CI están autorizados mediante la aprobación final registrada abajo. Al preparar la revisión, HEAD y origin/main conservaban `720f0fa4c54cb9c7673fc4da26ba21e8b6f3e56a`, antes de publicar 02D.

| Comando o comprobación | Resultado |
|---|---|
| `docker compose --env-file .env.docker exec -T api composer quality` | Pint correcto en 164 archivos; PHPStan nivel 8 sin errores; OpenAPI válido y documento malformado rechazado; 92 pruebas Unit/Feature, 2072 aserciones |
| `docker compose --env-file .env.docker exec -T api composer test:integration` | 45 pruebas, 902 aserciones en PostgreSQL/PostGIS real; regresiones Identity/Platform/Marketplace correctas |
| `docker compose --env-file .env.docker exec -T -e TRAEPE_INTEGRATION_TESTS=1 api vendor/bin/pest --ci tests/Integration/LocalPersistedCoveragePostgisTest.php` | 5 pruebas, 109 aserciones; repetidas tras corregir un chequeo redundante señalado por análisis estático |
| `docker compose --env-file .env.docker exec -T api php artisan marketplace:local-persisted-coverage 01ARZ3NDEKTSV4RRFFQ69G5FAZ 0 0` | Comando real: market_not_found, zone_id null y versión técnica correcta; salida 0, tres claves públicas |
| Comando real con `--` y límites negativos -180/-90 | Argumentos posicionales aceptados; market_not_found y salida mínima correctos |
| Inventario de tablas antes/después del comando real | countries/markets/service_zones mantienen conteo 0; postgis_lib_version confirma 3.5.7; sin datos/filas sensibles en salida |
| `docker compose --env-file .env.docker exec -T api php tests/Support/verify-foundation.php` | HTTP real, sonda inline, nuevo diagnóstico persistido sobre base vacía, privacidad y fundación correctos; sin credenciales en logs técnicos ni bases temporales restantes |
| Salud Docker | Ocho servicios saludables: api, nginx, postgres, redis, minio, mailpit, horizon y reverb |
| Revisión de archivos, diff, LF, enlaces y escaneo de secretos | 20 archivos del checkpoint revisados; sin cambios ajenos al alcance ni secretos |

Total sin contar repeticiones: **137 pruebas y 2974 aserciones**. Se agregan 14 pruebas: tres Unit, seis Feature y cinco Integration. El analizador detectó un is_string redundante para un argumento obligatorio de tipo string; se retiró ese chequeo y se repitieron calidad y las cinco pruebas espaciales afectadas, sin debilitar configuración ni validación ULID.

Las pruebas verifican ULID, límites y no finitos, resultado mínimo, errores genéricos, gate de comando previamente registrado y adaptador antes de SQL. Procesos nuevos production/staging prueban ausencia de comando/binding. Integración diferencia mercado ausente y mercado sin zonas; interior/exterior, bordes/esquinas, hueco y proximidad, componentes MultiPolygon, ejes, prioridades y empates. Dos mercados con polígonos coincidentes prueban que la prioridad del otro mercado nunca influye. Hashes de filas sintéticas antes/después confirman ausencia de cambios; la consulta del caso de uso es una única lectura con tres bindings, sin otras tablas privadas. HTTP, consola y caso de uso inline continúan ignorando borradores persistidos.

## Operación local

Desde la raíz, con el stack local existente y únicamente puntos sintéticos:

```powershell
docker compose --env-file .env.docker exec -T api php artisan marketplace:local-persisted-coverage 01ARZ3NDEKTSV4RRFFQ69G5FAZ 0 0
```

Como la base de desarrollo permanece vacía, devuelve market_not_found. No insertar mercados/zonas ni importar mapas para obtener otro estado. Selected/outside/ambiguous sobre datos persistidos se ejercitan exclusivamente en las bases temporales propias de integración. Para argumentos negativos, usar `--` antes de los argumentos posicionales, como en el comando inline anterior.

## Archivos y límites

20 archivos del checkpoint:

- Seis archivos nuevos utilizados en Marketplace: MarketPublicId, LocalPersistedZoneSource, LocalPersistedCoverageResult, LocalPersistedCoverageProbe, PostgisLocalPersistedZoneSource y ProbeLocalPersistedCoverage; provider existente actualizado.
- Tres pruebas nuevas: LocalPersistedCoverageTest, LocalPersistedCoverageCommandTest y LocalPersistedCoveragePostgisTest; verify-foundation actualizado.
- Nueve Markdown: README de API, índice documental, arquitectura, modelo de datos, registro DP-029, especificación Marketplace, README Sprint 02, plan y esta evidencia.

No se agregan dependencias, migraciones, seeds, usuarios/grants, API, administración ni datos reales. Las bases de prueba se crean y eliminan de forma aislada; no se resetea desarrollo ni se retiran volúmenes. DP-001 sigue abierta: estados/tipos operativos, activación, horarios, reglas vigentes, tarifas, ETA, checkout y proveedores requieren decisiones e implementación posteriores. La base draft/fixture y su política forward-only se conservan.

## Aprobación y publicación autorizadas

El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la pregunta «¿Apruebas 02D y autorizas commit, push y CI?», después de presentar la implementación y su evidencia local. Esta respuesta aprueba el checkpoint y autoriza publicar exclusivamente sus 20 archivos revisados, conservando el diagnóstico local de lectura, las tablas de desarrollo vacías y DP-001 abierta.

La publicación se realiza desde main mediante commit y push normales. La ejecución de GitHub Actions debe corresponder al SHA publicado y completar calidad, integración, reinicio/persistencia y limpieza. El resultado remoto se informa al cerrar la publicación.

## Resultado de publicación

Publicado en `34148c043335b6c33d540a0f696ccd4a7435d2d2` mediante commit/push normales de los 20 archivos aprobados. HEAD y origin/main coincidieron y el repositorio quedó limpio al cerrar 02D. [GitHub Actions 38080736037](https://github.com/nromero96/traepe/actions/runs/38080736037) terminó con success para ese SHA; construcción/migraciones, calidad/integración, reinicio/persistencia y limpieza completaron correctamente. El estado completed/success se volvió a consultar al preparar 02E. La solicitud posterior de continuar no amplía por sí sola el acceso autorizado a los borradores.
