# Propuesta de Checkpoint 02E — Diagnóstico persistido HTTP autorizado

Fecha: 10 de octubre de 2026. **Estado: aprobado mediante DP-030; implementado y validado localmente; checkpoint, commit, push y CI aprobados por el usuario.** Véase [evidencia de 02E](checkpoint-02e-evidence.md).

02D está publicado en `34148c043335b6c33d540a0f696ccd4a7435d2d2`, con [CI correcta](https://github.com/nromero96/traepe/actions/runs/38080736037), incluidos calidad, integración, reinicio/persistencia y limpieza. El usuario solicitó continuar. El diagnóstico persistido aprobado tiene acceso de consola; su aprobación excluía una API nueva.

## Decisión requerida

Maestro v1.6 §§32–34.1 y 58–60; DP-026 a DP-029 y [ADR-006](../../architecture/decisions/ADR-006-marketplace-geographic-foundation.md). Las sondas públicas de 02A/02B leen polígonos inline. Las tablas de 02C son privadas de Marketplace y sus borradores no constituyen cobertura operativa. Exponerlos por HTTP exige aprobar tanto la ampliación de acceso como su autorización; la sesión de un usuario por sí sola no concede este permiso.

DP-030 aprueba una capacidad técnica exclusiva para consultar el diagnóstico local de borradores por HTTP, con sesión y autorización de Identity. El usuario respondió «aprobar y continuar» a la propuesta completa el 10 de octubre de 2026, antes de implementar. No introduce acceso público a los borradores ni permisos administrativos operativos. Commit/push/CI se solicitarán con el resultado terminado.

## Alcance propuesto

- GET `/api/v1/marketplace/local-persisted-coverage-probe`, exclusivamente local/testing. Recibe `market_public_id`, `longitude` y `latitude` por query; ULID y punto WGS84 con los rangos aprobados, sin normalización silenciosa.
- Sesión cookie del flujo local existente de Identity. El servidor obtiene el actor de la sesión; una cabecera Authorization, una credencial de Horizon o IDs/permisos enviados por el cliente no habilitan acceso. No se crean tokens ni otro mecanismo de autenticación.
- Capacidad exacta `marketplace.local.persisted_coverage.read`, scope técnico `platform` con ULID fijo `01ARZ3NDEKTSV4RRFFQ69G5FB2` y recurso técnico `marketplace.local.persisted_coverage` con ULID fijo `01ARZ3NDEKTSV4RRFFQ69G5FB3`. Son referencias sintéticas del diagnóstico, resueltas en el servidor; no identifican una plataforma operativa ni un mercado real.
- La capacidad permite consultar cualquier mercado draft indicado por ULID dentro de este ejercicio local. Cada consulta geográfica queda restringida al mercado solicitado; no existe herencia desde scopes merchant/branch, comodín ni permiso concedido por conocer el ULID. La autorización es del diagnóstico técnico completo, no una autorización comercial por mercado.
- Reutilizar DP-026: actor activo, deny por defecto, deny vigente prevalece, scope exacto y expiración `now >= expiry` con reloj UTC del servidor. La capacidad anterior `identity.local.probe` no concede esta nueva lectura.
- Gate de entorno antes de sesión, autenticación, limitador, DI y SQL. Fuera de local/testing devuelve 404, incluso si se reutiliza una caché de rutas creada en local. El adaptador de autorización también deniega otros entornos antes de consultar Identity.
- Sin sesión válida devuelve 401; sin capacidad válida devuelve 403 antes de validar el mercado/punto o consultar Marketplace. El controlador valida la entrada después de autorizar y resuelve el caso de uso de cobertura después de validar. Un fallo de infraestructura devuelve error genérico, sin SQL ni entrada reflejada.
- Limitador técnico de 30 consultas por minuto por actor autenticado, con presupuesto separado de OTP, de la sonda de autorización y de la cobertura inline. Devuelve 429 con Retry-After. No autoriza operación comercial ni fija un límite productivo.
- Reutilizar LocalPersistedCoverageProbe de 02D sin cambiar su consulta ni criterios espaciales: mercado draft, zonas propias draft/fixture, geography nativa, bordes incluidos, prioridad máxima y rechazo de empate. Una lectura autorizada devuelve 200 para los cuatro resultados técnicos; `market_not_found` sigue siendo un resultado diagnóstico, sin revelar nombres, polígonos o IDs internos.
- Respuesta estándar del maestro con recurso `local_persisted_coverage_probe`, ID técnico `local-persisted-coverage-v1`, atributos cerrados `status` y `zone_id` nullable y correlation_id. Estados: market_not_found, outside, selected y ambiguous. No devuelve coordenadas, actor, permisos, bigint ni una promesa de servicio. Respuestas sin almacenamiento en caché.

## Límite entre módulos

Application de Marketplace define un puerto utilizado para autorizar el actor autenticado; no importa Laravel ni consulta Identity. Su adaptador en Infrastructure utiliza los contratos Application públicos ya existentes de Identity: AuthenticatedActorDirectory, AuthorizationDirectory y PermissionService, con los DTOs de autorización de ese contrato.

Marketplace aporta un ResourceContextResolver propio para el recurso/scope técnicos fijos. Su PermissionService se compone específicamente con ese resolver, sin sustituir el binding del diagnóstico de Identity. Identity conserva la propiedad de sus consultas privadas y de la política DP-026. Marketplace conserva la propiedad de markets/service_zones. No se realizan joins entre tablas de ambos módulos ni se trasladan reglas verticales a Shared.

El controlador permanece delgado: recibe actor de sesión, solicita autorización, valida formato, invoca el caso de uso existente y transforma la respuesta. La política de selección permanece en Domain y la consulta geográfica en Infrastructure.

## Datos y operación local

No se crean migraciones, seeds, comandos de asignación, usuarios, roles ni grants en desarrollo. Las tres tablas geográficas siguen vacías. Usuarios sintéticos, sesiones, permisos y borradores necesarios para pruebas positivas solo existen en bases temporales propias de integración y se retiran con ellas. El endpoint instalado en desarrollo no tiene un acceso positivo preconcedido.

La lectura no modifica datos de negocio, permisos ni borradores. La sesión y el limitador pueden actualizar sus metadatos técnicos existentes; no se presenta esta ruta como una transacción sin ninguna escritura técnica. No se modifica el flujo OTP, el consentimiento, las credenciales técnicas, los comandos 02A/02D ni la sonda HTTP 02B.

No se habilitan `/markets/resolve`, administración, activación, estados/tipos operativos, reglas vigentes, horarios, comercios/sucursales, tarifas, ETA, checkout ni proveedores. DP-001 permanece abierta. No se instalan dependencias ni se generan aplicaciones.

## Aceptación y validación

1. Puerto/adaptador utilizados, límites modulares y contrato de autorización documentados. Pruebas con fixtures verifican las constantes, denegación y ausencia de consultas privadas desde Marketplace.
2. Feature: sesión ausente, permiso ausente, capacidad/scope incorrectos, actor bloqueado, deny y expiración; manipulación de actor/scope/recurso/reloj no influye. Rechazos previos a consulta de cobertura; ULID, finitud, ejes y rangos; respuesta cerrada, correlación, errores genéricos, no-store y limitador independiente.
3. OpenAPI describe ruta, parámetros, sesión requerida, estados técnicos y errores 401/403/404/422/429/500. Validación oficial del contrato y pruebas de respuestas reales.
4. Production/staging en procesos nuevos: ruta ausente. Caché creada en local y reutilizada fuera de local: 404 antes de sesión, autorización y SQL, también con entrada inválida y cabeceras de autenticación. No aparecen bindings positivos fuera del entorno permitido.
5. PostgreSQL/PostGIS y Identity reales en bases propias desde cero: sesión cookie real del flujo OTP local, permiso exacto concedido solo en la base temporal, lectura autorizada y denegaciones. Dos mercados prueban aislamiento y los cuatro resultados; regresión de semántica geography de 02D. Logout/revocación y bloqueo impiden repetir la lectura. Comparar datos de negocio y grants antes/después, admitiendo solo metadatos técnicos de sesión/limitador.
6. Privacidad: canarios de coordenadas, cookies/cabeceras y entrada inválida no aparecen en logs; ningún error refleja credenciales, SQL o datos privados. No descargar ni imprimir logs completos.
7. Desarrollo: comprobación HTTP real mediante Nginx devuelve 401 sin sesión; no crea datos geográficos ni usuarios/permisos. Mantener fundación, ocho servicios saludables y ausencia de bases temporales, sin resets ni eliminación de volúmenes.
8. Pint, PHPStan nivel 8, OpenAPI, Unit/Feature, Integration, revisión del diff, enlaces y secretos. Actualizar especificación/API/seguridad/arquitectura y evidencia del checkpoint según la implementación terminada.

La aprobación inicial de DP-030 autorizó únicamente implementar y validar este plan. Después de presentar el resultado terminado, el usuario respondió «continuar, aprobado» a la aprobación final del checkpoint y a su commit/push/CI el 10 de octubre de 2026. Véase [registro de aprobación](checkpoint-02e-evidence.md#aprobación-y-publicación-autorizadas). No se autoriza despliegue productivo.
