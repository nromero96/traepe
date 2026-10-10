# Evidencia de Checkpoint 02G — Base vacía de comercios y sucursales

Fecha: 10 de octubre de 2026. **Estado: implementado y validado localmente; cierre y commit/push/CI autorizados explícitamente.**

## Autorización y trazabilidad

El usuario aprobó el [plan 02G](checkpoint-02g-plan.md) mediante «Apruebo» el 10 de octubre de 2026. [DP-032](../../architecture/decisions/pending-decisions.md#dp-032--base-local-vacía-de-comercios-y-sucursales) y [ADR-007](../../architecture/decisions/ADR-007-marketplace-commercial-foundation.md) registran propiedad, restricciones y límites aprobados. Maestro v1.6 §§3.1, 9, 32–34, 36, 52–56, 60, 72 y 78–79. No se reinterpretan los estados/acciones operativos de T-03/A-06.

Base: main / origin/main `3c9448c37c3736a155cd794b3f9bd2861e557796`, 02F publicado con [CI completa correcta](https://github.com/nromero96/traepe/actions/runs/38091316706); se comprobó nuevamente run/job y los cuatro pasos obligatorios sobre ese SHA al preparar 02G. La evidencia de 02F incorpora ahora ese resultado. La aprobación inicial permitió implementar/probar y aplicar localmente dos tablas vacías; la autorización final de publicación se registra al cierre. Ninguna aprobación habilita operación productiva.

## Resultado y archivos

Una migración aditiva/transaccional crea merchants y branches, privadas de Marketplace. IDs bigint internos y public_id ULID estricto/único, timestamps UTC requeridos, version>=1 y status solo draft. Merchant conserva legal_name/trade_name requeridos/no vacíos tras btrim, tax_id varchar(64) y risk_level varchar(40) exclusivamente NULL. Branch tiene merchant_id/market_id requeridos con FK restrict del mismo módulo; name/timezone explícitos, geography(Point,4326) válida/no vacía/2D y finita en rangos, con GIST. Índices de public_id y alcances (merchant_id, market_id)/(market_id).

Un comercio puede tener varias sucursales en uno o distintos mercados; varios comercios comparten mercado. No unique de nombres ni pareja merchant/market, no herencia/default de timezone ni mercado impuesto a Merchant. Sin deleted_at, membresías, contratos, horarios, configuración, historial de transiciones o API de mutación. Down es forward-only.

No se crean modelos, puertos, controladores, providers o carpetas sin uso. API/OpenAPI/snapshot y scopes Identity no cambian. Sondas 02A–02E y creación fija 02F no consultan/modifican registros comerciales; esto se prueba con filas sintéticas testigo y listener de consultas que registra únicamente un contador, sin SQL/valores.

Archivos del manifiesto (18):

- [apps/api/database/migrations/2026_10_10_000005_create_marketplace_commercial_foundation.php](../../../apps/api/database/migrations/2026_10_10_000005_create_marketplace_commercial_foundation.php)
- [apps/api/tests/Integration/CommercialFoundationTest.php](../../../apps/api/tests/Integration/CommercialFoundationTest.php)
- [apps/api/tests/Integration/GeographicFoundationTest.php](../../../apps/api/tests/Integration/GeographicFoundationTest.php)
- [apps/api/tests/Integration/LocalCoverageHttpPostgisTest.php](../../../apps/api/tests/Integration/LocalCoverageHttpPostgisTest.php)
- [apps/api/tests/Integration/LocalCoveragePostgisTest.php](../../../apps/api/tests/Integration/LocalCoveragePostgisTest.php)
- [apps/api/tests/Support/verify-foundation.php](../../../apps/api/tests/Support/verify-foundation.php)
- [docs/README.md](../../../docs/README.md)
- [docs/architecture/README.md](../../../docs/architecture/README.md)
- [docs/architecture/data-model/README.md](../../../docs/architecture/data-model/README.md)
- [docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md](../../../docs/architecture/decisions/ADR-007-marketplace-commercial-foundation.md)
- [docs/architecture/decisions/README.md](../../../docs/architecture/decisions/README.md)
- [docs/architecture/decisions/pending-decisions.md](../../../docs/architecture/decisions/pending-decisions.md)
- [docs/architecture/security/README.md](../../../docs/architecture/security/README.md)
- [docs/specifications/marketplace-and-purchase.md](../../../docs/specifications/marketplace-and-purchase.md)
- [docs/sprints/sprint-02/README.md](../../../docs/sprints/sprint-02/README.md)
- [docs/sprints/sprint-02/checkpoint-02f-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02f-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02g-evidence.md](../../../docs/sprints/sprint-02/checkpoint-02g-evidence.md)
- [docs/sprints/sprint-02/checkpoint-02g-plan.md](../../../docs/sprints/sprint-02/checkpoint-02g-plan.md)

## Pruebas y validaciones

| Validación | Resultado |
|---|---|
| Pint | 193 archivos correctos |
| PHPStan nivel 8 | Sin errores |
| OpenAPI 3.0.3 / API 1.5.0 y snapshot v1 existentes | Correctos; negativos del lint rechazados |
| Unit / Feature completas | 150 pruebas, 3011 aserciones |
| Integration completa PostgreSQL/PostGIS | 65 pruebas, 1581 aserciones |
| Total | **215 pruebas, 4592 aserciones** |
| Nuevos casos de CommercialFoundationTest | 8 casos: esquema, relaciones, rechazo, geografía, independencia, forward-only y aplicación aditiva |
| Fundación HTTP/DB | Correcta; seis tablas Marketplace vacías, ninguna base temporal remanente |
| Runtime FPM | Archivos propios y liveness correctos |
| Puertas negativas Pest/Pint/PHPStan | Fallos controlados rechazados con exit 1 |
| Alcance, formato documental y secretos | 18 archivos del manifiesto; diff/UTF-8 correctos, sin secretos ni cambios fuera del alcance |
| Enlaces locales | 179 referencias verificadas |
| Maestro v1.6 y staging | Hash original conservado; staging vacío |
| Servicios locales | Ocho saludables |

Comandos ejecutados en Docker con .env.docker ignorado: `composer quality`, `composer test:integration`, `composer format:check`, `php tests/Support/verify-foundation.php`, `verify-runtime-permissions.php` y `verify-quality-gates.php`. No dependencias instaladas, aplicaciones generadas ni infraestructura modificada.

Cobertura proporcional a la migración:

- Tabla vacía/tipos/nulabilidad/defaults/longitudes/índices, timestamps UTC, ULID válido/único y FK exclusivamente dentro de Marketplace con eliminación restrict.
- Varios comercios/sucursales/mercados, nombres repetidos permitidos, timezone explícito, defaults draft/version=1 y coordenadas sintéticas longitud/latitud conservadas.
- Rechazos de nombres/timezone vacíos, campos requeridos NULL, tax_id/risk_level no NULL, estados operativos/version inválida, identificadores inválidos/duplicados, referencias desconocidas y eliminación de raíz/mercado referenciado. CHECK también se verifica en UPDATE.
- Point nativo SRID 4326/2D/válido/no vacío; tipo, SRID, dimensiones y no finitos rechazados. PostGIS puede rechazar un literal no finito en el parser (XX000) antes del CHECK (23514); ambas vías impiden filas y solo se informa SQLSTATE.
- Una base temporal adicional propia aplica todas las migraciones anteriores, crea un fixture 02F con usuario/grant sintéticos y guarda hashes de todas las tablas previas. Aplicar 02G conserva esos hashes, agrega únicamente dos tablas vacías y un registro de migración; segunda ejecución sin cambios. Solo se elimina la base aleatoria propia al terminar.
- Down rechazado conserva registros sintéticos. Las pruebas geográficas anteriores exigen ahora tablas comerciales aprobadas vacías y conservan ausencia de otras tablas operativas; la consulta inline se comprueba independiente de las nuevas tablas.
- Con filas comerciales testigo: consola/HTTP inline y persistidos, creación fija 02F y replay no consultan/modifican merchants/branches. Las regresiones completas mantienen CSRF, sesión/OTP real, DP-026, idempotencia/concurrencia y aislamiento de mercado.

## Aplicación local preservando desarrollo

Después de las pruebas, un verificador temporal ignorado tomó conteos de todas las tablas previas, aplicó la migración y comparó: únicamente se añadieron merchants/branches vacías y un registro de migración. No desapareció ninguna tabla, y todos los conteos previos se conservaron, incluidos Identity/Platform. La segunda migrate indicó Nothing to migrate. El archivo temporal propio se eliminó por ruta literal.

Countries, markets, service_zones, marketplace_local_fixture_operations, merchants y branches conservan 0 registros. No se crearon usuarios, roles, grants ni datos positivos en desarrollo. Todos los casos positivos vivieron en bases temporales propias eliminadas al finalizar; sin reset, rollback local ni eliminación de volúmenes.

Fundación por Nginx mantiene liveness/readiness/correlación, Horizon anónimo 401, sonda persistida 401 y creación anónima sin CSRF 419, sin cambios de datos/claims. Ausencia de configuración operativa, catálogo/inventario/Ordering/Payments y logs técnicos sin credenciales. Ocho servicios locales saludables: api, nginx, postgres, redis, minio, mailpit, horizon y reverb.

## Límites y riesgos pendientes

DP-001 y demás decisiones productivas siguen abiertas. No hay onboarding, API comercial, administración/MFA, membresías, contratos/comisiones, horarios, cobertura por sucursal, configuración, geocodificación, catálogo o stock. Fiscalidad/riesgo y estados operativos requerirán especificación/migración/autorización futuras; solo NULL/draft no constituye evaluación favorable ni operación abierta.

El tipo geography puede normalizar valores fuera de rango al convertir: los CHECK verifican lo almacenado. Un futuro límite de escritura deberá validar las coordenadas originales con GeographicPoint antes del cast. La base no habilita entrada de ubicación. Down es forward-only; revertir datos requiere un plan revisado.

## Aprobación y publicación autorizadas

Después de presentar el resultado terminado y sus validaciones, el usuario respondió «Si autorizo» el 10 de octubre de 2026 a la solicitud explícita de commit, push y CI de 02G. Esta autorización satisface el cierre reservado en el plan y la restricción de [AGENTS.md](../../../AGENTS.md). Se publica exclusivamente el manifiesto de 18 archivos; no autoriza despliegue ni operación real.

Este registro precede al commit. El SHA publicado y el resultado de GitHub Actions se comprobarán sobre ese commit exacto y se comunicarán al finalizar, incluidos build/migración, calidad/integración, reinicio/persistencia y limpieza.
