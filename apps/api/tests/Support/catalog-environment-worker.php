<?php

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Request::create('/'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$url = '/api/v1/catalog/local-draft-catalogs';
$registered = true;
try {
    $app['router']->getRoutes()->match(Request::create($url, 'POST'));
} catch (NotFoundHttpException) {
    $registered = false;
}
$ports = [LocalDraftCatalogAccess::class, LocalDraftCatalogWriter::class, LocalDraftCatalogStore::class, LocalDraftMerchantSource::class];
$result = ['environment' => $app->environment(), 'registered' => $registered, 'bindings' => array_map(fn ($port) => $app->bound($port), $ports), 'statuses' => []];
$touched = array_fill(0, 6, false);
foreach ([StartSession::class, ValidateCsrfToken::class] as $index => $middleware) {
    $mock = Mockery::mock($middleware);
    $mock->shouldReceive('handle')->andReturnUsing(function () use (&$touched, $index) {
        $touched[$index] = true;
        throw new RuntimeException('Non-local middleware must not execute.');
    });
    $app->instance($middleware, $mock);
}
foreach ($ports as $index => $port) {
    $app->bind($port, function () use (&$touched, $index) {
        $touched[$index + 2] = true;
        throw new RuntimeException('Non-local dependency must not resolve.');
    });
}
foreach ([['POST', $url, '{}'], ['GET', $url.'/01ARZ3NDEKTSV4RRFFQ69G5FAZ', '']] as [$method, $path, $body]) {
    $request = Request::create($path, $method, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        'HTTP_IDEMPOTENCY_KEY' => 'private-key', 'HTTP_AUTHORIZATION' => 'Bearer private-header', 'HTTP_COOKIE' => 'private-cookie'], content: $body);
    $response = $kernel->handle($request);
    $result['statuses'][] = $response->getStatusCode();
    $kernel->terminate($request, $response);
}
$result['touched'] = $touched;
echo json_encode($result, JSON_THROW_ON_ERROR);
