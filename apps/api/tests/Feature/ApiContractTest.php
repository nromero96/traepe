<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Health\DependencyProbe;
use App\Shared\Http\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    public function test_health_resource_has_a_server_generated_correlation_id_even_without_accept_header(): void
    {
        $this->mock(DependencyProbe::class)->shouldReceive('check')->andReturn(true);
        $response = $this->withHeader('X-Correlation-ID', 'untrusted-input')->get('/api/v1/health/ready')->assertOk();
        $id = $response->json('meta.correlation_id');
        $this->assertTrue(Str::isUlid($id));
        $this->assertSame($id, $response->headers->get('X-Correlation-ID'));
        $response->assertJsonPath('data.type', 'health')->assertJsonPath('data.id', 'ready');
        $response->assertJsonPath('data.attributes.status', 'ready');
        $second = $this->getJson('/api/v1/health/ready');
        $this->assertNotSame($id, $second->json('meta.correlation_id'));
    }

    public function test_not_found_and_method_not_allowed_are_json_and_preserve_http_semantics(): void
    {
        $this->get('/api/v1/nonexistent')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $response = $this->postJson('/api/v1/health/ready')->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
        $this->assertStringContainsString('GET', $response->headers->get('Allow'));
        $this->assertTrue(Str::isUlid($response->json('error.correlation_id')));
        $this->assertSame($response->json('error.correlation_id'), $response->headers->get('X-Correlation-ID'));
    }

    public function test_validation_errors_contain_field_details_and_no_submitted_values(): void
    {
        Route::post('/api/v1/fixture-validation', function (Request $request) {
            $request->validate(['value' => ['required', 'integer', 'between:1,3']]);
        });
        foreach ([[], ['value' => 'fake-sensitive-input'], ['value' => 4]] as $input) {
            $response = $this->postJson('/api/v1/fixture-validation', $input)->assertUnprocessable();
            $response->assertJsonPath('error.code', 'validation_failed')->assertJsonPath('error.details.0.field', 'value');
            $response->assertJsonPath('error.details.0.code', 'invalid');
            $this->assertStringNotContainsString('fake-sensitive-input', $response->getContent());
        }
    }

    public function test_internal_errors_are_sanitized_even_with_debug_enabled_and_rate_limits_preserve_retry_after(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/fixture-error', fn () => throw new class('fake-secret /internal/path') extends RuntimeException implements ShouldntReport {});
        $response = $this->getJson('/api/v1/fixture-error')->assertStatus(500)->assertJsonPath('error.code', 'internal_error');
        $this->assertSame(['error'], array_keys($response->json()));
        $this->assertStringNotContainsString('fake-secret', $response->getContent());
        $this->assertStringNotContainsString('/internal/path', $response->getContent());
        Route::get('/api/v1/fixture-limit', fn () => throw new HttpException(429, 'internal detail', null, ['Retry-After' => '9']));
        $this->getJson('/api/v1/fixture-limit')->assertStatus(429)->assertHeader('Retry-After', '9')->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_collection_cursor_contract_handles_empty_and_paginated_results(): void
    {
        Route::get('/api/v1/fixture-collection', fn (Request $request) => ApiResponse::collection($request, [], null, false));
        $response = $this->getJson('/api/v1/fixture-collection');
        $response->assertJsonPath('data', [])->assertJsonPath('meta.next_cursor', null)->assertJsonPath('meta.has_more', false);
        $this->assertStringContainsString('"filters":{}', $response->getContent());
        Route::get('/api/v1/fixture-page', fn (Request $request) => ApiResponse::collection($request, [
            ['type' => 'fixture', 'id' => (string) Str::ulid(), 'attributes' => []],
        ], 'opaque-cursor', true, ['kind' => 'fixture']));
        $this->getJson('/api/v1/fixture-page')->assertJsonCount(1, 'data')->assertJsonPath('meta.next_cursor', 'opaque-cursor')->assertJsonPath('meta.has_more', true)->assertJsonPath('meta.filters.kind', 'fixture');
    }
}
