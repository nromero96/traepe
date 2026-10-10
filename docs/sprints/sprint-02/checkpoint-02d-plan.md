# Propuesta de Checkpoint 02D — Diagnóstico geográfico persistido por mercado

Fecha: 10 de octubre de 2026. **Estado: aprobado mediante DP-029; implementado y validado localmente; checkpoint, commit, push y CI aprobados por el usuario.** Véase [evidencia de 02D](checkpoint-02d-evidence.md).

02C está aprobado/publicado en `720f0fa4c54cb9c7673fc4da26ba21e8b6f3e56a`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38078507497), incluidos reinicio/persistencia y limpieza. El usuario solicitó continuar. Marketplace es dueño de las tres tablas geográficas vacías conforme a DP-028 y ADR-006; mercados/zonas siguen limitados a draft y las zonas a fixture.

## Decisión requerida

Maestro v1.6 §§32–34.1, 55–56 y 64; DP-027 y ADR-006. El siguiente incremento propuesto conectará una lectura diagnóstica al esquema persistente, con aislamiento por mercado. La aprobación de 02C no convierte un borrador en cobertura elegible. Además, geography representa bordes geodésicos; no se debe sustituir la evaluación plana de las sondas existentes silenciosamente.

DP-029 requirió aprobar un diagnóstico nuevo, exclusivamente local/testing y de consola, que evalúe zonas draft/fixture de un mercado draft usando geography nativa. Antes de implementar, el usuario respondió «Aprobar plan 02D (recomendado)» a la propuesta completa. El resultado selected significa coincidencia del ejercicio técnico; no autoriza servicio, entrega ni checkout. La decisión amplía únicamente el alcance diagnóstico; la aprobación de commit/push/CI se solicitará con la evidencia terminada.

## Alcance propuesto

- Nuevo comando `marketplace:local-persisted-coverage {market_public_id} {longitude} {latitude}`. Lo ejecuta el desarrollador mediante la consola del entorno local; no existe acceso HTTP ni se conceden capacidades a usuarios.
- ULID válido y punto WGS84 finito con ejes/rangos de 02A. Validación antes de consultar PostgreSQL; nunca normalizar coordenadas fuera de rango.
- El adaptador de Marketplace consulta únicamente markets/service_zones propias. Una consulta parametrizada resuelve el mercado por public_id/status=draft y restringe las zonas por su FK, status=draft y zone_type=fixture. Un LEFT JOIN distingue mercado ausente de mercado existente sin coincidencias, con el snapshot consistente de una sola sentencia.
- Evaluar `ST_Covers(polygon, point::geography)` sobre el tipo nativo de 02C, complementado por `ST_DWithin(polygon, point::geography, 0)` para incluir bordes de huecos conforme al criterio aprobado; véase la constatación técnica siguiente. Sin convertir el polígono a geometry para seleccionar ni cambiar su esquema. Punto SRID 4326; conservar columna espacial/indexada y parámetros enlazados.
- Reutilizar LocalZoneSelectionPolicy para prioridad/empate. No duplicar reglas en el comando ni mezclar los candidatos inline con los persistidos.
- Registrar comando y puerto únicamente en local/testing; además denegar en tiempo de ejecución antes de DI/SQL si cambia el entorno. El adaptador también comprueba entorno antes de consultar. Producción/staging no ejecutan el diagnóstico.
- No escribir las tres tablas, crear fixtures en desarrollo, importar geometrías, agregar migraciones ni instalar dependencias. Datos sintéticos persistidos solo en bases temporales propias de prueba.
- Las sondas 02A/02B y local-coverage-v1 siguen evaluando sus tres polígonos inline. Su HTTP, contrato OpenAPI y resultados no cambian.

## Salida técnica propuesta

JSON con claves cerradas: fixture_version, status, zone_id. fixture_version es local-persisted-coverage-v1, un identificador técnico de contrato. Zone_id es ULID público únicamente en selected; null en los otros estados. No imprimir market_id interno, país, nombres, geometrías, coordenadas, SQL, bindings ni errores internos; no registrar entradas del comando en logs de aplicación.

| Estado | Significado local |
|---|---|
| market_not_found | El ULID válido no identifica un mercado draft consultable |
| outside | Mercado draft conocido sin zonas draft/fixture que cubran el punto |
| selected | Una zona propia tiene prioridad máxima única según DP-027 |
| ambiguous | Dos zonas propias distintas empatan en la prioridad máxima; ninguna elegida |

Los cuatro resultados son diagnósticos correctos y terminan con código 0. Formato/punto inválido, entorno denegado o fallo de infraestructura terminan con código distinto de 0 y mensaje genérico. No hay estados operativos ni una promesa de cobertura.

## Implementación y aceptación después de aprobar

1. Domain/Application puros: referencia pública validada, puerto con mercado explícito, caso de uso y resultado técnico. Interfaces de consola valida formato y transforma salida; Infrastructure realiza lectura espacial propia. Crear solo archivos utilizados.
2. Unit/Feature: validación de ULID/punto, mapeo de los cuatro estados, máxima prioridad/empate reutilizados, salida mínima, fallo genérico y gate antes de resolver/consultar, incluso con comando previamente registrado.
3. PostgreSQL/PostGIS real, en bases propias desde cero: mercado inexistente, mercado sin zonas, interior/exterior, bordes/esquinas, hueco y borde, MultiPolygon con componentes separados, prioridades/empates y ejes explícitos.
4. Aislamiento: otro mercado con una zona coincidente de prioridad mayor no influye en el mercado solicitado. Nunca devolver bigint ni consultar tablas privadas de Identity/Platform. Demostrar que el diagnóstico no modifica filas y que las sondas previas continúan ignorando los borradores persistidos.
5. Probar una diferencia conocida entre geometry plana y geography nativa para hacer explícita la semántica del nuevo diagnóstico; no exigir que todos sus bordes reproduzcan la geometría plana anterior.
6. Desarrollo: tablas inicialmente y finalmente vacías; ejecutar el nuevo comando con ULID técnico inexistente y obtener market_not_found sin crear datos. Validar fundación y salud Docker, sin resetear datos ni eliminar volúmenes.
7. Pint, PHPStan, OpenAPI existente, Unit/Feature, Integration, diff/enlaces/secretos y evidencia local. Presentar el checkpoint terminado antes de solicitar commit/push/CI; una aprobación de DP-029 no autoriza por sí misma publicar.

## Límites y referencias

### Constatación técnica durante la implementación aprobada

En el PostGIS 3.5.7 instalado, una consulta de lectura con un MultiPolygon sintético con hueco devuelve ST_Covers=false para el punto exactamente sobre su borde meridiano, mientras ST_DWithin(..., 0)=true y ST_Distance=0. Por ello se conserva ST_Covers y se añade la comprobación nativa de distancia cero en la misma sentencia. Es una corrección técnica para cumplir bordes incluidos, sin cambiar el criterio aprobado ni añadir buffer o distancia positiva. Se prueban ambos lados del borde y el interior del hueco. No se utiliza ST_Intersects con tolerancia ni ST_Distance redondeada como predicado.

[ST_DWithin 3.5](https://postgis.net/docs/manual-3.5/ST_DWithin.html) documenta geography y uso de índices; el [código oficial fijado 3.5.7](https://github.com/postgis/postgis/blob/3.5.7/postgis/geography_measurement.c) compara la distancia con el umbral indicado en geography_dwithin. La consulta de prueba y la regresión espacial fundamentan la aplicación con umbral 0; no se infiere una regla operativa nueva.

DP-001 sigue abierta. Estados/tipos operativos, activación, horarios, reglas vigentes, moneda/timezone del piloto, comercios/sucursales, tarifas, ETA, checkout y proveedores externos permanecen fuera del incremento. No se introduce administración ni transición draft→active.

[ST_Covers de PostGIS 3.5](https://postgis.net/docs/manual-3.5/ST_Covers.html) admite geography y bordes inclusivos. [Modelo espacial y geography](https://postgis.net/docs/manual-3.5/using_postgis_dbmanagement.html#PostGIS_Geography) documenta la diferencia frente al plano cartesiano. Estas fuentes respaldan la técnica; no sustituyen aprobación del alcance ni de reglas operativas.
