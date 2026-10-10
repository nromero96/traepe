<?php

use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Request::create('/'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$url = '/api/v1/marketplace/local-persisted-coverage-probe';
$registered = true;
try {
    $app['router']->getRoutes()->match(Request::create($url));
} catch (NotFoundHttpException) {
    $registered = false;
}
$result = ['environment' => $app->environment(), 'registered' => $registered, 'access_bound' => $app->bound(LocalPersistedCoverageAccess::class), 'source_bound' => $app->bound(LocalPersistedZoneSource::class), 'statuses' => []];
$touched = [false, false, false];
$session = Mockery::mock(StartSession::class);
$session->shouldReceive('handle')->andReturnUsing(function () use (&$touched) {
    $touched[0] = true;
    throw new RuntimeException('Non-local diagnostic must not start a session.');
});
$app->instance(StartSession::class, $session);
foreach ([1 => LocalPersistedCoverageAccess::class, 2 => LocalPersistedZoneSource::class] as $index => $service) {
    $app->bind($service, function () use (&$touched, $index) {
        $touched[$index] = true;
        throw new RuntimeException('Non-local diagnostic must not resolve dependencies.');
    });
}
foreach (['', '?market_public_id=invalid&longitude=private-input', '?market_public_id=01ARZ3NDEKTSV4RRFFQ69G5FAZ&longitude=0&latitude=0'] as $query) {
    $request = Request::create($url.$query, 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer private-header-canary', 'HTTP_COOKIE' => 'private-cookie-canary']);
    $response = $kernel->handle($request);
    $result['statuses'][] = $response->getStatusCode();
    $kernel->terminate($request, $response);
}
$result['touched'] = $touched;
echo json_encode($result, JSON_THROW_ON_ERROR);
