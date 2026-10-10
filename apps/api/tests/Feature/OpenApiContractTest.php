<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Health\DependencyProbe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use stdClass;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    private array $document;

    protected function setUp(): void
    {
        parent::setUp();
        $path = is_file(base_path('../docs/api/openapi.yaml'))
            ? base_path('../docs/api/openapi.yaml') : base_path('../../docs/api/openapi.yaml');
        // JSON is a YAML 1.2 subset. Native parsing needs no added tooling package.
        $this->document = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_openapi_structure_references_and_routes_are_consistent(): void
    {
        $this->assertSame('3.0.3', $this->document['openapi']);
        $this->assertNotEmpty($this->document['info']['title']);
        $this->assertNotEmpty($this->document['info']['version']);
        $operations = [];
        $documented = [];
        foreach ($this->document['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $this->assertContains($method, ['get', 'post']);
                $this->assertNotContains($operation['operationId'], $operations);
                $operations[] = $operation['operationId'];
                $this->assertNotEmpty($operation['responses']);
                foreach ($operation['responses'] as $code => $response) {
                    $this->assertMatchesRegularExpression('/^[1-5][0-9]{2}$/', (string) $code);
                    $resolved = isset($response['$ref']) ? $this->resolve($response['$ref']) : $response;
                    $this->assertNotEmpty($resolved['description']);
                }
                $documented[] = strtoupper($method).' '.$path;
                $this->assertNotNull(Route::getRoutes()->match(Request::create($path, strtoupper($method))));
            }
        }
        sort($documented);
        $expected = ['GET /api/v1/auth/me', 'GET /api/v1/health/ready', 'GET /sanctum/csrf-cookie', 'POST /api/v1/auth/logout', 'POST /api/v1/auth/otp/request', 'POST /api/v1/auth/otp/verify', 'POST /api/v1/technical/broadcasting/auth'];
        $this->assertSame($expected, $documented);
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/')) {
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $actual[] = $method.' /'.$route->uri();
                }
            }
        }
        sort($actual);
        $this->assertSame(array_values(array_filter($expected, fn ($route) => str_contains($route, '/api/v1/'))), $actual);
        $walk = function (array $node) use (&$walk): void {
            if (isset($node['$ref'])) {
                $this->assertIsArray($this->resolve($node['$ref']));
            }
            if (isset($node['required'], $node['properties'])) {
                foreach ($node['required'] as $property) {
                    $this->assertArrayHasKey($property, $node['properties']);
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($this->document);
    }

    public function test_real_health_and_error_payloads_conform_to_the_published_schemas(): void
    {
        foreach ([true, false] as $healthy) {
            $this->mock(DependencyProbe::class)->shouldReceive('check')->andReturn($healthy);
            $response = $this->getJson('/api/v1/health/ready')->assertStatus($healthy ? 200 : 503);
            $this->assertSchema(json_decode($response->getContent()), $this->document['components']['schemas']['ReadinessResponse']);
        }
        foreach ([
            $this->getJson('/api/v1/nonexistent')->assertNotFound(),
            $this->postJson('/api/v1/health/ready')->assertStatus(405),
            $this->postJson('/api/v1/technical/broadcasting/auth', [])->assertUnauthorized(),
        ] as $response) {
            $this->assertSchema(json_decode($response->getContent()), $this->document['components']['schemas']['ErrorResponse']);
        }
    }

    private function resolve(string $reference): array
    {
        $this->assertStringStartsWith('#/', $reference);
        $value = $this->document;
        foreach (explode('/', substr($reference, 2)) as $key) {
            $this->assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }

    // Focused response-contract checks, not a general-purpose OpenAPI validator.
    private function assertSchema(mixed $value, array $schema): void
    {
        if (isset($schema['$ref'])) {
            $this->assertSchema($value, $this->resolve($schema['$ref']));

            return;
        }
        $this->assertTrue(match ($schema['type']) {
            'object' => $value instanceof stdClass,
            'array' => is_array($value),
            'string' => is_string($value),
            default => false,
        });
        if (isset($schema['const'])) {
            $this->assertSame($schema['const'], $value);
        }
        if (isset($schema['enum'])) {
            $this->assertContains($value, $schema['enum']);
        }
        if (isset($schema['pattern'])) {
            $this->assertMatchesRegularExpression('~'.$schema['pattern'].'~', $value);
        }
        if ($schema['type'] === 'object') {
            $properties = get_object_vars($value);
            foreach ($schema['required'] ?? [] as $key) {
                $this->assertArrayHasKey($key, $properties);
            }
            foreach ($properties as $key => $property) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    $this->assertArrayHasKey($key, $schema['properties']);
                }
                if (isset($schema['properties'][$key])) {
                    $this->assertSchema($property, $schema['properties'][$key]);
                }
            }
        }
        if ($schema['type'] === 'array') {
            foreach ($value as $item) {
                $this->assertSchema($item, $schema['items']);
            }
        }
    }
}
