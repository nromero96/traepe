# Sprint 02 — Marketplace local

Fecha: 10 de octubre de 2026. Continuación tras Identity local 01A–01F aprobado/publicado; [CI de 01F](https://github.com/nromero96/traepe/actions/runs/38048298696) correcto para `38684843baf5fafbd2ef53d3f2bd9665ed106927`. El usuario eligió cobertura ficticia local y aprobó DP-027.

## Checkpoint 02A

Origen: maestro v1.6 §§4.1, 32–34.1, 51–54 y 64; módulos aprobados en AGENTS.md. Materializar Marketplace solo con código utilizado: Domain valida punto WGS84 y selecciona candidato; Application define caso de uso y puerto; Infrastructure consulta PostGIS; Interfaces ofrece diagnóstico por consola. Sin dependencia de tablas de Identity ni de otro módulo.

Punto: números finitos, longitud entre -180 y 180, latitud entre -90 y 90, ejes explícitos. No se redondea ni normaliza silenciosamente un punto inválido. Las coordenadas pueden usar float; no se introducen importes monetarios.

DP-027: incluir interiores y bordes; seleccionar la zona de mayor prioridad; sin candidatos devuelve outside, empate entre zonas distintas de máxima prioridad devuelve ambiguous sin zona seleccionada. Una referencia repetida de la misma zona no equivale a dos zonas distintas. El resultado es diagnóstico de un fixture, no una promesa de servicio.

El adaptador usa tres polígonos sintéticos junto al origen matemático (0,0), identificadores públicos ULID fijos y prioridades enteras. Incluye solapamientos y un hueco para verificar comportamiento espacial. No carga mapas/distritos reales ni escribe tablas, migraciones o seeds.

## Aceptación

- Dominio puro, DTOs inmutables, política independiente del orden; límites, números no finitos, ausencia, prioridad y empate probados.
- PostGIS real: interior, exterior, esquina/borde, hueco y su borde, solapamiento, empate y orden longitud/latitud. Polígonos válidos, 2D, SRID 4326.
- Puerto y comando solo local/testing; guard de ejecución deniega otros entornos antes de resolver servicios o consultar PostgreSQL.
- Comando recibe formato válido, devuelve únicamente versión del fixture, estado y ULID técnico, nunca coordenadas, SQL o detalles internos. No agrega API ni privilegios.
- Regresiones de Identity/Platform, límites modulares, Pint, PHPStan, OpenAPI y bases aisladas correctos.
- Documentación, diff, escaneo de secretos y evidencia de ejecución listos para revisión. Commit/push separados de la autorización de implementación.

## Puertas operativas

DP-001 sigue abierta: no están definidos ciudad/país, distritos o polígonos reales, horarios de lanzamiento, timezone y moneda del piloto. DP-010 mantiene los adaptadores productivos de mapas/geocodificación. Este ejercicio no define zone_rules, disponibilidad por sucursal, tarifas, ETA, geocodificación ni cobertura aplicable a checkout.

## Estado

02A está implementado y validado localmente bajo el alcance ficticio aprobado. Pasaron Pint (148 archivos), PHPStan nivel 8, OpenAPI, 67 pruebas Unit/Feature con 1505 aserciones y 32 de integración con 565 aserciones: 99 pruebas y 2070 aserciones en total. PostGIS real verifica los criterios de DP-027; la fundación y los ocho servicios Docker siguen correctos.

La [evidencia de 02A](checkpoint-02a-evidence.md) registra archivos, comandos, resultados y límites. El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la aprobación final de 02A y a su commit, push y validación en GitHub Actions. Se publicó en `edc1b0ffefd882e0c008342952d17a63f32afd0e`; [CI correcta](https://github.com/nromero96/traepe/actions/runs/38070750987), incluidos reinicio/persistencia y limpieza. DP-001 permanece abierta.

## Checkpoint 02B — HTTP local

Continuación solicitada por el usuario tras publicar 02A. GET `/api/v1/marketplace/local-coverage-probe` valida longitud/latitud de query y reutiliza el caso de uso existente. Lectura pública del fixture exclusivamente local/testing, sin sesión, privilegios, escrituras ni datos operativos. No implementa `/markets/resolve`. Mantiene DP-027 y DP-001 abierta.

El contrato OpenAPI 1.3.0 describe el recurso técnico y sus errores; las pruebas incluyen formato/rangos, correlación, privacidad, throttling independiente de Identity, PostGIS real y caché local de rutas reutilizada en producción/staging. La fundación verifica HTTP a través de Nginx. Pasaron Pint (153 archivos), PHPStan nivel 8, OpenAPI y 116 pruebas con 2584 aserciones; ocho servicios saludables. [Evidencia y operación de 02B](checkpoint-02b-evidence.md). El usuario aprobó 02B y autorizó su commit, push y CI el 10 de octubre de 2026 mediante «Apruebo y autorizo». Se publicó en `b4b81f738790a796cfc0b27e4752c80b849f13a2`; [CI correcta](https://github.com/nromero96/traepe/actions/runs/38075664329), incluidos reinicio/persistencia y limpieza.

## Checkpoint 02C — Base geográfica vacía

El usuario aprobó el [plan de 02C](checkpoint-02c-plan.md) mediante «Aprobar plan 02C (recomendado)», resolviendo DP-028 antes de implementar. [ADR-006](../../architecture/decisions/ADR-006-marketplace-geographic-foundation.md) fija countries, markets y service_zones privadas de Marketplace, vacías y sin activación, mercados/zonas solo draft y zone_type solo fixture. La migración aditiva PostgreSQL/PostGIS conserva bigint/ULID, FK restrict, timestamps UTC, integridad espacial SRID 4326 e índices.

02C está implementado y validado localmente: Pint (155 archivos), PHPStan nivel 8, OpenAPI, 83 pruebas Unit/Feature con 1973 aserciones y 40 de integración con 793 aserciones; total 123 pruebas y 2766 aserciones. La aplicación local agregó tres tablas vacías y un registro de migración, conservó los conteos existentes y su segunda ejecución no produjo cambios. Fundación y ocho servicios saludables. Las sondas 02A/02B ignoran borradores persistidos. [Evidencia de 02C](checkpoint-02c-evidence.md). El usuario aprobó el checkpoint y autorizó commit, push y CI el 10 de octubre de 2026 mediante «Apruebo y autorizo»; se publicó en `720f0fa4c54cb9c7673fc4da26ba21e8b6f3e56a`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38078507497), incluidos reinicio/persistencia y limpieza. DP-001 sigue abierta para el piloto real.

## Checkpoint 02D — Diagnóstico persistido por mercado

El usuario solicitó continuar tras publicar 02C y aprobó el [plan de 02D](checkpoint-02d-plan.md) mediante «Aprobar plan 02D (recomendado)». El comando nuevo local/testing evalúa únicamente zonas draft/fixture del mercado draft indicado por ULID, usando geography nativa y los criterios de prioridad/bordes/empate del ejercicio local. Sin escrituras, seeds, administración, API nueva ni activación. Las tablas de desarrollo permanecen vacías; fixtures persistidos solo en bases temporales propias. Las sondas 02A/02B conservan sus polígonos inline.

DP-029 registra la ampliación aprobada respecto de la base vacía y la diferencia entre evaluación geodésica y plana. ST_Covers se complementa con distancia geodésica 0 para incluir un borde de hueco que PostGIS 3.5.7 excluye; los puntos próximos en el interior del hueco siguen rechazados.

02D está implementado y validado localmente: Pint (164 archivos), PHPStan nivel 8, OpenAPI, 92 pruebas Unit/Feature con 2072 aserciones y 45 de integración con 902 aserciones; total 137 pruebas y 2974 aserciones. Fundación y ocho servicios saludables. El comando real devuelve market_not_found sobre la base local vacía; las tres tablas mantienen conteo 0 y no quedan bases temporales. [Evidencia de 02D](checkpoint-02d-evidence.md). El usuario aprobó el checkpoint y autorizó commit, push y CI el 10 de octubre de 2026 mediante «Apruebo y autorizo»; la publicación y comprobación remota se ejecutan bajo esa autorización. DP-001 y la operación real siguen pendientes.
