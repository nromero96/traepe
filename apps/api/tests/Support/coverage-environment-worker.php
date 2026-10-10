<?php

use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Request::create('/'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$url = '/api/v1/marketplace/local-coverage-probe';
$registered = true;
try {
    $app['router']->getRoutes()->match(Request::create($url));
} catch (NotFoundHttpException) {
    $registered = false;
}
$result = ['environment' => $app->environment(), 'cached' => $app->routesAreCached(), 'registered' => $registered, 'source_bound' => $app->bound(LocalZoneSource::class), 'statuses' => [], 'errors' => []];
foreach (['', '?longitude=0.5&latitude=0.5'] as $query) {
    $request = Request::create($url.$query, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $result['statuses'][] = $response->getStatusCode();
    $result['errors'][] = $body['error']['code'] ?? null;
    $kernel->terminate($request, $response);
}
// Public statuses only; no points, request details, credentials, cookies or logs.
echo json_encode($result, JSON_THROW_ON_ERROR);
