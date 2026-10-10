# Checkpoint 02B — Sonda HTTP de cobertura ficticia

Fecha: 10 de octubre de 2026. Continuación de 02A aprobado/publicado en `edc1b0ffefd882e0c008342952d17a63f32afd0e`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38070750987). Origen: maestro v1.6 §§4.1, 34.1, 58–60 y 64; alcance ficticio y criterios de DP-027 ya aprobados.

## Alcance y contrato

02B agrega GET `/api/v1/marketplace/local-coverage-probe` únicamente en local/testing. Es una lectura pública de tres polígonos sintéticos, coherente con el descubrimiento previo a autenticar al visitante del maestro §58. No requiere identidad, sesión, CSRF ni privilegios; no accede a datos privados. No implementa la ruta operativa `/markets/resolve` ni habilita mercados reales. DP-001 sigue abierta.

El controlador valida exclusivamente parámetros de query `longitude` y `latitude`, requeridos, numéricos y dentro de los rangos WGS84 aprobados. Invoca el caso de uso de 02A; no copia la regla de selección ni agrega políticas. La salida 200 usa `data.type=local_coverage_probe`, `data.id=local-coverage-v1`, `attributes.status` y `attributes.zone_id`, además de `meta.correlation_id`. El ID identifica el fixture técnico, no una entidad persistida. selected/outside/ambiguous mantienen exactamente los criterios de 02A.

Errores de formato/rango: 422 estándar sin reflejar valores. Fallos internos: 500 sanitizado. El límite técnico reutiliza 30 solicitudes por minuto por IP, con prefijo propio para no consumir el cupo existente de Identity; 429 conserva Retry-After. La respuesta del diagnóstico tiene Cache-Control no-store/private. El middleware verifica el entorno antes de throttling, validación, resolución del puerto o PostgreSQL, incluso si se reutiliza una caché de rutas local en producción/staging.

Las coordenadas solo se usan como datos del ejercicio; no se devuelven ni se incluyen en logs. Nginx ya usa un formato JSON sin URL, query, IP, headers o cuerpo, y la aplicación conserva el procesador de logs de vocabulario cerrado. No introducir direcciones/personas reales ni utilizar el resultado como disponibilidad comercial, precio o ETA.

## Criterios de aceptación

- Envoltura REST, correlación, estados y ULID técnico de zona correctos; sin coordenadas ni escrituras.
- Validación antes del puerto; límites, valores no finitos, arrays, ausencia y formato inválido rechazados.
- GET público de lectura, con HEAD implícito; métodos de mutación rechazados y sesión/privilegios no creados.
- Gate fuera de local/testing y caché local reutilizada probado con procesos frescos.
- PostgreSQL/PostGIS real conserva los resultados de 02A por HTTP; tablas/datos operativos sin cambios.
- Contrato OpenAPI versionado consistente con rutas y respuestas reales.
- Calidad, integración, fundación, privacidad de logs, revisión del diff y escaneo de secretos correctos.

## Estado

02B está implementado, validado localmente y aprobado por el usuario. Su commit, push y CI están autorizados mediante la aprobación registrada abajo. Al preparar la revisión, HEAD y origin/main conservaban `edc1b0ffefd882e0c008342952d17a63f32afd0e`, antes de publicar 02B.

## Validaciones ejecutadas

| Comando o comprobación | Resultado |
|---|---|
| `docker compose --env-file .env.docker exec -T api composer quality` | Pint correcto en 153 archivos; PHPStan nivel 8 sin errores; OpenAPI válido y documento malformado rechazado; 83 pruebas Unit/Feature, 1973 aserciones |
| `docker compose --env-file .env.docker exec -T api composer test:integration` | 33 pruebas, 611 aserciones; regresiones Identity/Platform y PostGIS por HTTP correctas |
| `docker compose --env-file .env.docker exec -T -e TRAEPE_INTEGRATION_TESTS=1 api vendor/bin/pest --ci tests/Integration/LocalCoverageHttpPostgisTest.php` | Repetida tras aislar el prefijo del limitador: 1 prueba, 46 aserciones correctas |
| `docker compose --env-file .env.docker exec -T api php tests/Support/verify-foundation.php` | Salud real, correlación, errores seguros y sonda HTTP Nginx/PHP-FPM/PostGIS correctos; sin sesión, secretos en logs ni bases temporales restantes |
| HTTP desde Windows y log de Nginx correlacionado | 200 selected para fixture B; las últimas líneas del contenedor incluyen el registro con status/correlación/duración y omiten URL/query/coordenadas |
| `docker compose --env-file .env.docker ps` | Ocho servicios saludables: api, nginx, postgres, redis, minio, mailpit, horizon y reverb |
| Revisión de archivos, `git diff --check`, LF, enlaces y escaneo de secretos | 17 archivos del checkpoint revisados; sin secretos ni cambios fuera del alcance |

Total sin contar repeticiones: **116 pruebas y 2584 aserciones**. 02B agrega 17 pruebas: quince Feature, una de contrato y una de integración. La prueba de separación de cupos usa explícitamente el entorno testing para observar el limitador de OTP después del bypass CSRF unitario normal; las pruebas existentes de CSRF y cookies por HTTP real siguen pasando. El limitador y sus datos de pruebas están aislados en array, sin limpiar Redis de desarrollo.

Las pruebas de caché escriben únicamente en un directorio temporal propio, construyen rutas en local y ejecutan procesos nuevos production/staging: ruta cacheada registrada pero rechazada 404, puerto sin binding. Sin caché, producción no registra la ruta. Se elimina exclusivamente la caché propia; no se modifica la de ejecución.

## Operación local

Con el stack existente en local, usar únicamente coordenadas sintéticas:

```powershell
$probeUrl = 'http://127.0.0.1:8000/api/v1/marketplace/local-coverage-probe'
Invoke-RestMethod ($probeUrl + '?longitude=3.5&latitude=0.5')
Invoke-RestMethod ($probeUrl + '?longitude=10&latitude=10')
Invoke-RestMethod ($probeUrl + '?longitude=5.5&latitude=1.5')
```

Resultados respectivos: selected/fixture B, outside/null y ambiguous/null. Ajustar el puerto si TRAEPE_HTTP_PORT local difiere de 8000. Las tres evaluaciones devuelven 200 como diagnósticos; outside/ambiguous no autorizan una entrega. El comando de consola de 02A sigue disponible sin cambios.

## Archivos y límites

17 archivos del checkpoint:

- Dos nuevos de Interfaces/Http: LocalCoverageEnvironment y LocalCoverageProbeController; provider Marketplace existente actualizado.
- Pruebas nuevas LocalCoverageHttpTest y LocalCoverageHttpPostgisTest, worker nuevo coverage-environment-worker; OpenApiContractTest y verify-foundation actualizados.
- Contrato `docs/api/openapi.yaml` versión 1.3.0.
- Ocho Markdown: README de API, índice documental, contrato API, arquitectura, decisiones, especificación Marketplace, README Sprint 02 y esta evidencia.

Se reutilizan Domain, Application, polígonos de 02A y procesadores de logs existentes. No se agregan dependencias, migraciones, seeds, grants, usuarios, mercados, zonas operativas, tarifas, horarios, ETA ni frontend. DP-001 y los proveedores productivos permanecen pendientes; este resultado no es cobertura operativa. Los datos/volúmenes de desarrollo se conservan y las bases PostgreSQL de pruebas se eliminan al terminar cada caso.

## Aprobación y publicación autorizadas

El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la pregunta: «¿Apruebas 02B y autorizas commit, push y CI?». Esta respuesta aprueba el checkpoint y autoriza publicar sus 17 archivos revisados, conservando el alcance ficticio local y DP-001 abierta.

La publicación se realiza desde main mediante commit y push normales. La ejecución de GitHub Actions debe corresponder al SHA publicado y completar calidad, integración, reinicio/persistencia y limpieza. El resultado remoto se informa al cerrar la publicación.
