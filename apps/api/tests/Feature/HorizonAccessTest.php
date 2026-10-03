<?php

namespace Tests\Feature;

use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    public function test_dashboard_denies_missing_or_wrong_credentials_even_in_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/horizon')->assertUnauthorized();
        $this->getJson('/horizon/api/stats')->assertUnauthorized();
        $this->withBasicAuth('technical', 'incorrect')->get('/horizon')->assertUnauthorized();
    }

    public function test_dashboard_accepts_technical_credential_only_in_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $password = str_repeat('fixture-', 5);
        config(['technical.password' => $password]);
        $this->withBasicAuth('technical', $password)->get('/horizon')->assertOk();

        $this->app->detectEnvironment(fn () => 'production');
        $this->withBasicAuth('technical', $password)->get('/horizon')->assertUnauthorized();
    }

    public function test_empty_password_never_grants_access(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['technical.password' => '']);
        $this->withBasicAuth('technical', '')->get('/horizon')->assertUnauthorized();
    }
}
