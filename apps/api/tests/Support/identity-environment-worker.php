<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->instance('request', Request::create('/'));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$result = ['environment' => $app->environment(), 'cached' => $app->routesAreCached(), 'routes' => []];
foreach ([
    ['POST', '/api/v1/auth/otp/request'], ['POST', '/api/v1/auth/otp/verify'],
    ['GET', '/api/v1/auth/me'], ['POST', '/api/v1/auth/logout'],
    ['GET', '/api/v1/identity/local-authorization-probe'],
] as [$method, $url]) {
    $request = Request::create($url, $method, server: ['HTTP_ACCEPT' => 'application/json']);
    $registered = true;
    try {
        $app['router']->getRoutes()->match($request);
    } catch (NotFoundHttpException) {
        $registered = false;
    }
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $result['routes'][] = ['registered' => $registered, 'status' => $response->getStatusCode(), 'error' => $body['error']['code'] ?? null];
    $kernel->terminate($request, $response);
}
// Only public route status; no credentials, cookies, requests or logs.
echo json_encode($result, JSON_THROW_ON_ERROR);
