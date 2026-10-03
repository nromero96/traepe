<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    // Only explicitly configured origins may use cookie/session authentication.
    'stateful' => array_values(array_filter(explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost:8000,127.0.0.1:8000')))),
    'guard' => ['web'],
    // No token issuance flow or token table is provided by checkpoint 00D.
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
