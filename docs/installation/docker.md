# Docker — Checkpoint 00B

## Requisitos

- Docker Desktop con backend WSL 2.
- Docker Compose v5.
- Puertos locales configurables disponibles.
- Copia local de `.env.docker.example` como `.env.docker`, con contraseñas exclusivas de desarrollo.

XAMPP, Apache, MySQL y PHP de Windows no son necesarios para trae.pe. Composer, PHP-FPM, PostgreSQL/PostGIS y Redis se ejecutan dentro de contenedores Linux.

## Puertos

| Servicio | Host por defecto | Red Docker |
|---|---|---|
| HTTP | `127.0.0.1:8000` | `nginx:80` |
| PostgreSQL | `127.0.0.1:54320` | `postgres:5432` |
| Redis | `127.0.0.1:63790` | `redis:6379` |

Los puertos host se cambian con `TRAEPE_HTTP_PORT`, `TRAEPE_POSTGRES_PORT` y `TRAEPE_REDIS_PORT`. PostgreSQL y Redis nunca se publican en todas las interfaces.

Los cuatro servicios se comunican por `traepe_internal`, una red Docker aislada. Nginx, PostgreSQL y Redis se conectan además a `traepe_host` únicamente para habilitar sus publicaciones ligadas a `127.0.0.1`; PHP-FPM no publica puertos al host.

## Inicio

```powershell
Copy-Item .env.docker.example .env.docker
# Completar contraseñas locales y TRAEPE_APP_KEY con la APP_KEY de apps/api/.env.
docker compose --env-file .env.docker config
docker compose --env-file .env.docker build --pull --no-cache api
docker compose --env-file .env.docker up -d postgres redis api
docker compose --env-file .env.docker exec api composer install --no-interaction --prefer-dist
docker compose --env-file .env.docker exec api php artisan migrate --force
docker compose --env-file .env.docker up -d nginx
```

## Operación segura

- No versionar `.env.docker` ni `apps/api/.env`.
- La clave real de Laravel se inyecta mediante `TRAEPE_APP_KEY` desde `.env.docker`; ambos archivos que la contienen permanecen ignorados.
- No reutilizar credenciales productivas.
- No ejecutar `docker compose down --volumes` como parte de scripts normales.
- Los datos persisten en volúmenes con prefijo `traepe_`.
- Redis es caché/cola reconstruible; PostgreSQL es la fuente de verdad.
- Producción no debe heredar puertos, contraseñas o defaults locales.

## Comprobación

```powershell
docker compose --env-file .env.docker ps
curl.exe http://localhost:8000/up
docker compose --env-file .env.docker exec api php artisan test
```
