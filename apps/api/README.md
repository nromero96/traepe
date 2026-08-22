# trae.pe API

Backend maestro y API pura de trae.pe, construido con Laravel 13 y PHP 8.4.

## Estado

Checkpoint 00A contiene únicamente el framework y la configuración base. No incluye autenticación funcional, frontend, dominios comerciales ni servicios de infraestructura de checkpoints posteriores.

## Ejecución local 00A

Usa un runtime PHP 8.4 explícito:

```powershell
composer install
php artisan key:generate
php artisan config:clear
php artisan test
php artisan serve
```

Consulta [la guía de entorno](../../docs/installation/environment.md) antes de ejecutar. No uses el PHP 8.2 de XAMPP para este proyecto.
