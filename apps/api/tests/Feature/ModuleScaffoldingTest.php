<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Scaffolding\ModuleGenerator;
use App\Modules\Platform\Infrastructure\Scaffolding\ModuleScaffolder;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ModuleScaffoldingTest extends TestCase
{
    public function test_generator_creates_valid_layers_only_in_an_isolated_fixture_and_never_overwrites(): void
    {
        $root = sys_get_temp_dir().'/traepe-module-'.bin2hex(random_bytes(8));
        mkdir($root);
        $this->app->instance(ModuleGenerator::class, new ModuleScaffolder($root));
        try {
            $this->artisan('make:module Support')->assertSuccessful();
            foreach (['Domain', 'Application', 'Infrastructure', 'Interfaces'] as $layer) {
                $this->assertFileExists($root.'/Support/'.$layer.'/README.md');
            }
            $provider = $root.'/Support/Infrastructure/SupportServiceProvider.php';
            $before = hash_file('sha256', $provider);
            require $provider;
            $this->assertTrue(class_exists('App\\Modules\\Support\\Infrastructure\\SupportServiceProvider'));
            $this->artisan('make:module Support')->assertFailed();
            $this->assertSame($before, hash_file('sha256', $provider));
            foreach (['../escape', 'identity', 'Unapproved', 'Support\\Other'] as $name) {
                $this->artisan('make:module', ['name' => $name])->assertFailed();
            }
            $this->artisan('make:module Catalog')->assertSuccessful();
            $this->assertDirectoryExists($root.'/Catalog');
            mkdir($root.'/preserved');
            file_put_contents($root.'/preserved/marker.txt', 'preserve');
            symlink($root.'/preserved', $root.'/Ordering');
            $this->artisan('make:module Ordering')->assertFailed();
            $this->assertSame('preserve', file_get_contents($root.'/preserved/marker.txt'));
            unlink($root.'/Ordering');
        } finally {
            // Only this randomly named root, owned by this test, is removed.
            (new Filesystem)->deleteDirectory($root);
        }
        $this->assertDirectoryDoesNotExist($root);
        $this->assertSame(['Identity', 'Marketplace', 'Platform'], array_values(array_diff(scandir(app_path('Modules')), ['.', '..'])));
    }
}
