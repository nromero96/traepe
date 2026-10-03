<?php

namespace App\Modules\Platform\Interfaces\Console;

use App\Modules\Platform\Application\Scaffolding\ModuleGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class MakeModule extends Command
{
    protected $signature = 'make:module {name : Nombre exacto de un módulo aprobado}';

    protected $description = 'Crear la estructura de un módulo al comenzar su implementación';

    public function handle(ModuleGenerator $scaffolder): int
    {
        try {
            $scaffolder->generate((string) $this->argument('name'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Módulo creado. Registrar su provider explícitamente después de revisar el resultado.');

        return self::SUCCESS;
    }
}
