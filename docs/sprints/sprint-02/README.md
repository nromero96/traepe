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

La [evidencia de 02A](checkpoint-02a-evidence.md) registra archivos, comandos, resultados y límites. El 10 de octubre de 2026 el usuario respondió «Apruebo y autorizo» a la aprobación final de 02A y a su commit, push y validación en GitHub Actions. La publicación y comprobación de CI se ejecutan bajo esa autorización. DP-001 permanece abierta.
