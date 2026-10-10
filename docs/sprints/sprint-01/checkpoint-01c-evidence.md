# Checkpoint 01C — Directorio PostgreSQL de autorización

Fecha: 10 de octubre de 2026. Continuación autorizada tras 01B aprobado/publicado. Origen: maestro v1.6 §35 (roles/capacidades/asignaciones/excepciones), §§2.1 y 60 y reglas aprobadas DP-026. Implementación de lectura y pruebas; pendiente aprobación/publicación del checkpoint.

## Resultado y límites

AuthorizationDirectory tiene adaptador PostgresAuthorizationDirectory registrado en IdentityServiceProvider. isActive consulta el estado actual de users por ULID. rules filtra por el mismo actor activo y lee únicamente tablas privadas de Identity: asignaciones, relación rol-capacidad, capacidades y excepciones. Roles y grants se leen con UNION ALL en una sola sentencia, usando el mismo snapshot PostgreSQL. No hay caché de permisos.

Roles se proyectan como Allow en el alcance exacto de su asignación. Excepciones conservan allow/deny y expiración; PermissionService/PermissionPolicy de 01B deciden precedencia y límites temporales. Ninguna concesión se deduce de un rol global, email, nombre o estado active. user_id identifica al beneficiario de las asignaciones/excepciones; no se ofrece operación para emitirlas.

La migración aditiva crea identity_roles, identity_permissions, identity_role_permissions, identity_role_assignments e identity_permission_grants. Incluye bigint internos, ULID únicos, códigos únicos sin wildcard, FKs/indexes, asignación única por usuario/rol/scope, checks de scope/efecto/referencias y expiry timestamptz. Sin seeds ni asignaciones automáticas. No hay FK a tablas de comercio/sucursal: el módulo dueño deberá resolver/validar esas referencias mediante contrato, sin consulta privada cruzada.

El resolver de recursos continúa sin adaptador comercial; no se modifica ninguna ruta ni se utiliza el directorio para conceder acceso a API real. Scopes/recursos/capacidades de pruebas son ficticios. La representación definitiva de la plataforma y las políticas de escritura/administración quedan para el checkpoint que habilite asignaciones reales; este esquema no define esos valores ni actores operativos.

## Validación

composer quality: Pint 127 archivos, PHPStan nivel 8 sin errores, OpenAPI válido, 45 pruebas Unit/Feature y 1163 aserciones. Pruebas PostgreSQL: cinco nuevas de directorio, más regresión de Platform/Identity. Suite completa PostgreSQL: 18 pruebas y 144 aserciones. Total validado: 63 pruebas y 1307 aserciones.

Casos nuevos: binding del puerto, directorio vacío, actor desconocido/bloqueado, proyección de rol, deny persistido prevalente, expiración exacta, aislamiento entre usuarios, wildcard/efecto rechazados, FK de actor, scope/ULID inválidos, asignación duplicada y reejecución de migraciones. Los fixtures se crean únicamente en bases temporales propias que el test elimina.

## Archivos

- apps/api/app/Modules/Identity/Infrastructure/Authorization/PostgresAuthorizationDirectory.php.
- apps/api/app/Modules/Identity/Infrastructure/IdentityServiceProvider.php.
- apps/api/database/migrations/2026_10_10_000002_create_identity_authorization_directory.php.
- apps/api/tests/Integration/AuthorizationDirectoryTest.php.
- Evidencia e índice de Sprint 01; notas del modelo de datos.

## Riesgos y seguimiento

Migración forward-only: un rollback de datos requiere plan revisado. Las escrituras futuras de roles/grants requieren matriz de capacidades, validación de scope, autorización sensible/MFA, concurrencia/idempotencia y auditoría aprobadas; no se implementaron en 01C. Las lecturas no sustituyen el control transaccional al ejecutar una mutación sensible: la autorización deberá revalidarse dentro de su contexto de ejecución. Un snapshot coherente no es una garantía de autorización permanente.

No se instalaron dependencias, agregaron rutas, otorgaron privilegios ni eliminaron datos/volúmenes. GitHub Actions previo verde corresponde a 01B/ae3888d; 01C no está publicado ni validado remotamente. No se hizo commit ni push.
Migración aplicada localmente; las cinco tablas verificadas permanecen vacías. verify-foundation pasó y los ocho servicios están healthy. git diff --check correcto. Los volúmenes existentes se conservaron.

## Aprobación y publicación autorizada — 10 de octubre de 2026

El usuario respondió «Apruebo y autoizo» a la aprobación de 01C, commit/push y validación en GitHub Actions. La autorización cubre el directorio, migración, pruebas y documentación de este checkpoint. El resultado remoto se verificará para el SHA publicado antes de declararlo verde.
