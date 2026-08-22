# trae.pe API

Backend maestro y API pura de trae.pe, construido con Laravel 13 y PHP 8.4.

## Estado

Checkpoint 00B conecta el framework base con PostgreSQL/PostGIS y Redis mediante Docker Compose. No incluye autenticación funcional, frontend, dominios comerciales ni servicios de checkpoints posteriores.

## Ejecución local

Desde la raíz del repositorio, sigue la [guía Docker](../../docs/installation/docker.md). Composer y Artisan se ejecutan dentro del contenedor PHP:

```powershell
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec api composer install
docker compose --env-file .env.docker exec api php artisan migrate
docker compose --env-file .env.docker exec api php artisan test
```

La API queda disponible en `http://localhost:8000`. XAMPP, Apache, MySQL y el PHP instalado en Windows no son necesarios para trae.pe.
