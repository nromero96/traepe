# Entorno local — Checkpoints 00A y 00B

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

En 00B fueron sustituidas por `DB_CONNECTION=pgsql`, `CACHE_STORE=redis` y `QUEUE_CONNECTION=redis`. Continúan temporalmente `BROADCAST_CONNECTION=log`, `FILESYSTEM_DISK=local` y `MAIL_MAILER=log` hasta sus checkpoints autorizados. Consulta la [guía Docker](docker.md).

## Validación actual

```powershell
docker compose --env-file .env.docker config
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec api php artisan --version
docker compose --env-file .env.docker exec api php artisan test
```

La aplicación debe responder HTTP 200 en `http://localhost:8000` usando exclusivamente el stack Docker de 00B.

## Seguridad

- `APP_DEBUG=false` es el default versionado.
- Las sesiones se cifran.
- `.env.example` contiene marcadores vacíos, no credenciales.
- Ningún proveedor productivo se configura en 00A.
