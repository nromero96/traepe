# Entorno local — Sprint 00

## Versiones aprobadas

- PHP 8.4.
- Laravel 13.
- Composer 2.x.

Checkpoint 00B establece Docker Compose como entorno reproducible. XAMPP, Apache, MySQL y el PHP instalado en Windows no son necesarios para trae.pe. El runtime portátil usado durante 00A queda como antecedente local, no como dependencia del proyecto.

## Instalación de dependencias PHP

Ejecutar Composer dentro del contenedor PHP desde la raíz:

```powershell
docker compose --env-file .env.docker exec api composer install --no-interaction
```

El entorno de desarrollo desactiva `optimize-autoloader` por el costo observado en Windows. CI/producción podrán solicitar optimización explícita cuando se definan sus comandos.

## Configuración

1. Copiar `.env.example` a `.env`.
2. Generar una clave local con `php artisan key:generate` usando PHP 8.4.
3. No versionar `.env`; ya está excluido por `.gitignore`.
4. No colocar secretos reales en `.env.example`.
5. Copiar la `APP_KEY` local a `TRAEPE_APP_KEY` en `.env.docker`; ninguno de esos archivos se versiona.

Durante 00A, antes de PostgreSQL/Redis, se usaron temporalmente:

- `DB_CONNECTION=sqlite`;
- `QUEUE_CONNECTION=sync`;
- `CACHE_STORE=file`;
- `BROADCAST_CONNECTION=log`;
- `FILESYSTEM_DISK=local`;
- `MAIL_MAILER=log`;
- sesiones: `file`.

En 00B fueron sustituidas por `DB_CONNECTION=pgsql`, `CACHE_STORE=redis` y `QUEUE_CONNECTION=redis`. 00C agrega `BROADCAST_CONNECTION=reverb`, `FILESYSTEM_DISK=s3` y `MAIL_MAILER=smtp`. Consulta la [guía Docker](docker.md).

## Validación actual

```powershell
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec api php artisan --version
docker compose --env-file .env.docker exec api composer quality
```

La aplicación debe responder HTTP 200 en `http://localhost:8000/up` usando exclusivamente Docker. Para una copia nueva, seguir [instalación y calidad](README.md); el inicializador genera credenciales ignoradas sin sobrescribir entornos existentes.

## Seguridad

00C configura correo local: `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, sin credenciales SMTP. Compose usa un remitente ficticio `.test`; no hay relay externo. `TRAEPE_MAILPIT_HTTP_PORT=11825` configura la UI publicada exclusivamente en `127.0.0.1`.

DP-023 y DP-024 fueron resueltas por aprobación explícita. Completar en `.env.docker` con valores aleatorios independientes (mínimo 32 caracteres): `TRAEPE_TECHNICAL_PASSWORD`, `TRAEPE_REVERB_APP_KEY`, `TRAEPE_REVERB_APP_SECRET`, `TRAEPE_MINIO_ROOT_PASSWORD`, `TRAEPE_MINIO_APP_PASSWORD`. Mantener claves/passwords vacíos en las plantillas versionadas. Nunca imprimir `docker compose config` completo, porque interpola secretos: usar `config --quiet`.

La credencial técnica usa usuario `technical` mediante HTTP Basic, solo en local. Reverb usa ID `traepe-local`, host interno `reverb:8080`, esquema `http` y orígenes permitidos `localhost`/`127.0.0.1`. No enviar credenciales por parámetros de URL. MinIO usa root exclusivamente en servidor/inicializador y usuario `traepe-local-app` limitado a `traepe-local` en Laravel. El disco S3 configura endpoint interno `http://minio:9000`, región `us-east-1` y path-style. No utilizar estas plantillas locales en producción.

- `APP_DEBUG=false` es el default versionado.
- Las sesiones se cifran.
- `.env.example` contiene marcadores vacíos, no credenciales.
- Ningún proveedor productivo se configura en 00A.
