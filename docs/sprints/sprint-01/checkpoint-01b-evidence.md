# Checkpoint 01B — Núcleo de autorización

Fecha: 10 de octubre de 2026. DP-026 aprobada explícitamente por el usuario mediante «aprueba». Origen: maestro v1.6 §§2.1, 35 y 60. Implementación local preparada; pendiente aprobación del checkpoint y autorización de publicación.

## Resultado

- Identity/Domain/Authorization: Scope y ResourceReference con referencias públicas ULID; ResourceContext de servidor; PermissionEffect y PermissionRule inmutables; Decision con motivos técnicos cerrados y sin PII; PermissionPolicy determinista.
- Identity/Application/Authorization: PermissionService y puertos AuthorizationDirectory/ResourceContextResolver. Estado del actor y reglas se obtienen del directorio de Identity; contexto del recurso se obtiene del contrato de su dueño, nunca del cliente. No hay consultas a tablas de otros módulos.
- Actor inactivo denegado antes de resolver recursos/reglas. Recurso desconocido o distinto denegado; contexto debe coincidir con alcance. Sin allow aplicable: deny. Deny aplicable vigente prevalece, independientemente del orden.
- Coincidencia exacta de actor/capacidad/tipo de alcance/ID público. Sin herencia plataforma→comercio→sucursal ni wildcard. Las capacidades de rol se representan como Allow en el alcance de la asignación; no se crean roles reales.
- Expiración exacta now >= expiry. DateTimeImmutable compara instantes equivalentes aunque tengan offsets distintos. Instante explícito para pruebas; los futuros adaptadores deben obtenerlo del reloj del servidor, nunca de un request.

## Validación

`composer quality`: Pint 124 archivos; PHPStan nivel 8 sin errores; OpenAPI correcto; Unit/Feature 45 pruebas y 1156 aserciones. `composer test:integration`: 13 pruebas y 114 aserciones. Total: 58 pruebas, 1270 aserciones.

11 pruebas nuevas verifican concesión exacta, ausencia, orden allow/deny, actor ajeno, capacidad/scope incorrectos, ausencia de herencia, expiración exacta y zona horaria, actor bloqueado, recurso/contexto distinto o desconocido, resolución mediante puerto, ausencia de carga con actor inactivo, rechazo de wildcard y ausencia de estado retenido entre evaluaciones. Las suites de arquitectura y autenticación/OTP/consentimiento/sesión de 01A conservan su cobertura y pasan.

## Archivos modificados

Nuevos: siete archivos Domain/Authorization, tres Application/Authorization y tests/Unit/PermissionPolicyTest.php; plan y esta evidencia de 01B. Modificados: registro DP-026, índice del Sprint 01 y especificación navegable de seguridad. No se modifican migraciones, contratos HTTP, rutas ni datos; no se instalan dependencias ni cambian lockfiles.

## Límites de integración

El servicio no está registrado para autorizar rutas reales: sus puertos aún no tienen adaptadores persistentes ni resolvers comerciales. Todos los recursos/scopes y capacidades de prueba son ficticios. La representación del scope plataforma en esta fase es una referencia pública de fixture; su identidad real y cualquier mapeo comercial se concretarán al implementar persistencia. No hay endpoints para conceder roles, grants, superadmin, MFA ni privilegios reales.

Una decisión Allowed significa permiso en el alcance exacto del recurso resuelto; no introduce condiciones comerciales adicionales de propiedad individual, estado del negocio o acciones sensibles. Esas políticas pertenecen al módulo dueño del recurso y deben especificarse antes de integrar el servicio. Las operaciones sensibles mantienen el requisito maestro de reautenticación/MFA y auditoría; este bloque no las implementa.

GitHub Actions verde previo corresponde a 01A/8acfe86. 01B no se ha publicado ni validado remotamente. No se hizo commit, push ni despliegue.
## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «Apruebo y autorizo» a la aprobación de 01B, commit/push y validación en GitHub Actions. Se autoriza publicar este núcleo y sus documentos/pruebas. El resultado remoto se verificará para el SHA publicado antes de declararlo verde.
