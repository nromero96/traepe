<?php

namespace App\Modules\Platform\Infrastructure\Scaffolding;

use App\Modules\Platform\Application\Scaffolding\ModuleGenerator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class ModuleScaffolder implements ModuleGenerator
{
    public function __construct(private string $root) {}

    public function generate(string $name): string
    {
        if (! in_array($name, self::MODULES, true)) {
            throw new InvalidArgumentException('El nombre debe pertenecer a la lista arquitectónica aprobada.');
        }
        $directory = $this->root.'/'.$name;
        if (! is_dir($this->root) || is_link($this->root) || file_exists($directory) || is_link($directory)) {
            throw new RuntimeException('La raíz debe existir y el módulo no debe existir; no se sobrescribe.');
        }
        if (! mkdir($directory)) {
            throw new RuntimeException('No se pudo reservar el directorio del módulo.');
        }
        $createdFiles = [];
        $createdDirectories = [$directory];
        try {
            $documents = [
                'Domain' => 'Reglas y contratos puros. Sin Laravel, HTTP, Eloquent, colas ni proveedores externos.',
                'Application' => 'Casos de uso y puertos. Depende de Domain, nunca de Infrastructure ni Interfaces.',
                'Infrastructure' => 'Adaptadores que implementan puertos. Registrar explícitamente el provider en bootstrap/providers.php al comenzar la implementación.',
                'Interfaces' => 'HTTP, consola, jobs y eventos de entrada. Validar formato y delegar en Application.',
            ];
            foreach ($documents as $layer => $description) {
                $path = $directory.'/'.$layer;
                if (! mkdir($path)) {
                    throw new RuntimeException('No se pudo crear una capa.');
                }
                $createdDirectories[] = $path;
                $file = $path.'/README.md';
                $this->write($file, "# {$name}: {$layer}\n\n{$description}\n\nNo contiene reglas de negocio generadas.\n");
                $createdFiles[] = $file;
            }
            $file = $directory.'/Infrastructure/'.$name.'ServiceProvider.php';
            $this->write($file, "<?php\n\nnamespace App\\Modules\\{$name}\\Infrastructure;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nfinal class {$name}ServiceProvider extends ServiceProvider\n{\n}\n");
            $createdFiles[] = $file;
        } catch (Throwable $exception) {
            foreach (array_reverse($createdFiles) as $file) {
                unlink($file);
            }
            foreach (array_reverse($createdDirectories) as $path) {
                rmdir($path);
            }
            throw $exception;
        }

        return $directory;
    }

    private function write(string $path, string $contents): void
    {
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo sin sobrescribir.');
        }
        try {
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new RuntimeException('No se pudo completar el archivo.');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            unlink($path);
            throw $exception;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
