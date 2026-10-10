# Checkpoint 01B — Base de autorización de Identity local

Preparado el 10 de octubre de 2026, después de 01A aprobado/publicado y CI verde. Estado: reglas de DP-026 aprobadas explícitamente el 10 de octubre de 2026; implementación del núcleo preparada para revisión. No modifica la autenticación aprobada de 01A.

## Trazabilidad

Maestro v1.6 §§2.1, 35 y 60: capacidades, asignaciones de rol con alcance, excepciones concedidas/negadas con expiración y PermissionService(capability, scope, resource). No fija precedencia entre allow/deny ni herencia de scopes; resolverlas silenciosamente cambiaría seguridad.

## Alcance propuesto

Núcleo de autorización puro dentro de Identity Domain/Application, contrato PermissionService y pruebas de políticas mediante fixtures. Modelo explícito de actor, capacidad, alcance y recurso; el controlador nunca decide privilegios. Infrastructure resolverá únicamente datos propios de Identity; el módulo dueño de un recurso suministrará un contrato de contexto/alcance, sin consultas directas a tablas privadas.

En este primer bloque no se materializan comercios/sucursales, ni se asignan capacidades administrativas a usuarios. No hay endpoint para conceder roles/grants, superadmin, wildcard, frontend, proveedores, MFA ni acciones comerciales. Las pruebas usarán capacidades y recursos ficticios sin habilitar privilegios en la API existente.

## Política aprobada — DP-026

1. Ausencia de concesión válida: deny. Actor bloqueado: deny.
2. Una denegación explícita vigente del mismo actor/capacidad/alcance prevalece sobre una concesión o rol.
3. Concesiones y roles requieren coincidencia exacta de alcance; no hay herencia implícita plataforma→comercio→sucursal ni comodines.
4. Permiso expirado cuando now >= expiry; comparación en UTC.
5. El contexto del recurso debe provenir de un resolver del servidor y coincidir con el alcance solicitado. Datos de alcance/propiedad enviados por el cliente no constituyen autorización. Recurso/contexto desconocido: deny.
6. Recursos y scopes se representan con identificadores públicos; no se exponen IDs internos.

Estas reglas aprobadas no sustituyen matrices comerciales futuras ni definen roles operativos.

## Aceptación y validación

- Domain/Application sin Laravel, Eloquent, HTTP, Redis ni proveedores externos.
- Decisión explícita y determinista con motivo técnico cerrado, sin PII.
- Casos de concesión válida, ausencia, actor bloqueado, deny prevalente, alcance incorrecto, recurso ajeno/desconocido, expiración exacta y ausencia de herencia.
- Pruebas independientes del orden y del reloj del host mediante instante de evaluación explícito.
- Regresión completa de 01A, Pint, PHPStan nivel 8 y OpenAPI.
- Documentación de la decisión, diff y evidencia de checkpoint. Nuevas dependencias y publicación requieren autorización separada.

## Secuencia

Resolver DP-026; implementar núcleo/contratos; probar los casos y regresiones; entregar evidencia para aprobación de 01B. Persistencia y operaciones de asignación quedan para un checkpoint posterior con matriz de capacidades y auditoría aprobadas.