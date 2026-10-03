<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class SanctumFoundationTest extends TestCase
{
    public function test_stateful_api_requests_enforce_csrf_without_building_a_login_flow(): void
    {
        // Laravel normally skips CSRF in tests; local makes this a real middleware check.
        $this->app->detectEnvironment(fn () => 'local');
        Route::middleware('api')->post('/api/v1/fixture-csrf', fn () => response()->noContent());
        $this->withHeader('Origin', 'http://127.0.0.1:8000')
            ->postJson('/api/v1/fixture-csrf', [])
            ->assertStatus(419)->assertJsonPath('error.code', 'csrf_token_mismatch');
        $this->withSession(['_token' => 'fixture-csrf-token'])
            ->postJson('/api/v1/fixture-csrf', ['_token' => 'fixture-csrf-token'])->assertNoContent();
    }

    public function test_cookie_foundation_is_available_without_users_or_token_issuance(): void
    {
        $this->assertSame(['web'], config('sanctum.guard'));
        $this->assertContains(EnsureFrontendRequestsAreStateful::class, app('router')->getMiddlewareGroups()['api']);
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/fixture-protected', fn () => []);
        $this->getJson('/api/v1/fixture-protected')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/api/v1/health/ready', 'GET', [], [], [], ['HTTP_ORIGIN' => 'https://untrusted.example.test'])));
        $this->assertDirectoryDoesNotExist(app_path('Modules/Identity'));
        $this->assertFalse(in_array('Laravel\\Sanctum\\HasApiTokens', class_uses(User::class), true));
    }
}
