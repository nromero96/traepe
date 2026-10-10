<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\IdentityUser;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedCoverageAccess;
use App\Modules\Marketplace\Application\Coverage\LocalPersistedZoneSource;
use App\Modules\Marketplace\Application\Coverage\LocalZoneSource;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureAccess;
use App\Modules\Marketplace\Application\Fixtures\LocalFixtureWriter;
use App\Modules\Marketplace\Domain\Coverage\ZoneCandidate;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
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
        $expected = ['GET /api/v1/auth/me', 'GET /api/v1/health/ready', 'GET /api/v1/identity/local-authorization-probe', 'GET /api/v1/marketplace/local-coverage-probe', 'GET /api/v1/marketplace/local-persisted-coverage-probe', 'GET /sanctum/csrf-cookie', 'POST /api/v1/auth/logout', 'POST /api/v1/auth/otp/request', 'POST /api/v1/auth/otp/verify', 'POST /api/v1/marketplace/local-draft-fixtures', 'POST /api/v1/technical/broadcasting/auth'];
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

    public function test_local_coverage_payloads_conform_to_the_versioned_schema(): void
    {
        $source = $this->mock(LocalZoneSource::class);
        foreach ([[], [new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FAZ', 10)], [new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FAZ', 10), new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB0', 10)]] as $candidates) {
            $source->shouldReceive('matches')->once()->andReturn($candidates);
            $response = $this->getJson('/api/v1/marketplace/local-coverage-probe?longitude=0.5&latitude=0.5')->assertOk();
            $this->assertSchema(json_decode($response->getContent()), $this->document['components']['schemas']['LocalCoverageResponse']);
        }
        $invalid = $this->getJson('/api/v1/marketplace/local-coverage-probe?longitude=private-input&latitude=0')->assertUnprocessable();
        $this->assertSchema(json_decode($invalid->getContent()), $this->document['components']['schemas']['ErrorResponse']);
    }

    public function test_persisted_coverage_session_and_payload_contracts(): void
    {
        $url = '/api/v1/marketplace/local-persisted-coverage-probe';
        $this->assertSame([['LocalSessionCookie' => []]], $this->document['paths'][$url]['get']['security']);
        $unauthenticated = $this->getJson($url)->assertUnauthorized();
        $this->assertSchema(json_decode($unauthenticated->getContent()), $this->document['components']['schemas']['ErrorResponse']);
        config(['session.driver' => 'array']);
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $this->mock(LocalPersistedCoverageAccess::class)->shouldReceive('allows')->andReturn(true);
        $source = $this->mock(LocalPersistedZoneSource::class);
        $a = new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB2', 10);
        $b = new ZoneCandidate('01ARZ3NDEKTSV4RRFFQ69G5FB3', 10);
        foreach ([null, [], [$a], [$a, $b]] as $candidates) {
            $source->shouldReceive('matches')->once()->andReturn($candidates);
            $response = $this->getJson($url.'?market_public_id=01ARZ3NDEKTSV4RRFFQ69G5FAZ&longitude=0.5&latitude=0.5')->assertOk();
            $this->assertSchema(json_decode($response->getContent()), $this->document['components']['schemas']['LocalPersistedCoverageResponse']);
        }
    }

    public function test_fixture_post_and_snapshot_conform_to_the_closed_versioned_contract(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        config(['session.driver' => 'array']);
        $url = '/api/v1/marketplace/local-draft-fixtures';
        $this->assertSame([['LocalSessionCookie' => []]], $this->document['paths'][$url]['post']['security']);
        $this->assertSchema(json_decode($this->postJson($url, [])->assertUnauthorized()->getContent()), $this->document['components']['schemas']['ErrorResponse']);
        $this->actingAs((new IdentityUser)->forceFill(['id' => 11]), 'web');
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAZ';
        $this->mock(LocalDraftFixtureAccess::class)->shouldReceive('actor')->andReturn($id);
        foreach (FixtureProfile::cases() as $profile) {
            $operation = new FixtureOperation($id, $profile, $id, $id, [$id, '01ARZ3NDEKTSV4RRFFQ69G5FB0', '01ARZ3NDEKTSV4RRFFQ69G5FB1']);
            $this->mock(LocalFixtureWriter::class)->shouldReceive('execute')->once()->andReturn($operation);
            $response = $this->withHeader('Idempotency-Key', 'contract-key')->postJson($url, ['fixture_profile' => $profile->value])->assertCreated();
            $this->assertSchema(json_decode($response->getContent()), $this->document['components']['schemas']['LocalDraftFixtureResponse']);
        }
        $schema = json_decode(file_get_contents('/var/www/docs/api/schemas/marketplace-local-draft-fixture-operation.v1.json'), true, flags: JSON_THROW_ON_ERROR);
        unset($schema['$schema'], $schema['title']);
        $this->assertSame($schema, $this->document['components']['schemas']['LocalDraftFixtureResponse']['properties']['data']['properties']['attributes']);
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
        if ($value === null && ($schema['nullable'] ?? false)) {
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
            if (isset($schema['minItems'])) {
                $this->assertGreaterThanOrEqual($schema['minItems'], count($value));
            }
            if (isset($schema['maxItems'])) {
                $this->assertLessThanOrEqual($schema['maxItems'], count($value));
            }
            if ($schema['uniqueItems'] ?? false) {
                $this->assertSame(count($value), count(array_unique($value)));
            }
            foreach ($value as $item) {
                $this->assertSchema($item, $schema['items']);
            }
        }
    }
}
