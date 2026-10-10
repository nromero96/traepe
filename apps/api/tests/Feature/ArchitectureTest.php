<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Health\DependencyProbe;
use App\Modules\Platform\Infrastructure\Health\DependencyHealth;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ArchitectureTest extends TestCase
{
    public function test_platform_provider_boots_and_registered_port_resolves_to_its_adapter(): void
    {
        $this->assertInstanceOf(DependencyHealth::class, app(DependencyProbe::class));
        $this->artisan('list')->expectsOutputToContain('make:module')->assertSuccessful();
    }

    public function test_module_layers_and_shared_obey_their_dependency_boundaries(): void
    {
        foreach ((new Filesystem)->allFiles(app_path('Modules')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            foreach (token_get_all($file->getContents()) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $reference = ltrim($token[1], '\\');
                if (str_contains($path, '/Domain/')) {
                    $this->assertDoesNotMatchRegularExpression('/^(Illuminate|Laravel|Symfony)\\\\|\\\\(Application|Infrastructure|Interfaces)\\\\/', $reference, $path);
                }
                if (str_contains($path, '/Application/')) {
                    $this->assertDoesNotMatchRegularExpression('/^(Illuminate|Laravel|Symfony)\\\\|\\\\(Infrastructure|Interfaces)\\\\/', $reference, $path);
                }
                if (str_contains($path, '/Interfaces/')) {
                    $this->assertStringNotContainsString('\\Infrastructure\\', $reference, $path);
                }
                if (str_starts_with($reference, 'App\\Modules\\')) {
                    $module = explode('/Modules/', $path)[1];
                    $module = explode('/', $module)[0];
                    $this->assertStringStartsWith('App\\Modules\\'.$module.'\\', $reference, 'Unapproved cross-module dependency: '.$path);
                }
            }
        }
        foreach ((new Filesystem)->allFiles(app_path('Shared')) as $file) {
            $this->assertStringNotContainsString('App\\Modules\\', $file->getContents(), $file->getPathname());
        }
        $this->assertSame(['Identity', 'Platform'], array_values(array_diff(scandir(app_path('Modules')), ['.', '..'])));
    }
}
