<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';

foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache', 'storage/logs'] as $directory) {
    $path = $app->basePath($directory).'/permission-probe-'.bin2hex(random_bytes(8));
    try {
        if (! is_writable(dirname($path)) || file_put_contents($path, 'technical-probe') !== 15 || file_get_contents($path) !== 'technical-probe') {
            throw new RuntimeException('Runtime directory is not writable: '.$directory);
        }
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
echo "PASS: FPM user can write/read/remove its runtime files.\n";
$kernel = $app->make(Kernel::class);
$request = Request::create('/up');
$response = $kernel->handle($request);
$kernel->terminate($request, $response);
if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('Liveness rendering failed under the FPM user.');
}
echo "PASS: liveness renders successfully as the FPM user on native filesystem.\n";
