<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Health\DependencyProbe;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function test_readiness_and_each_dependency_failure_have_stable_sanitized_payloads(): void
    {
        foreach ([null, ...DependencyProbe::DEPENDENCIES] as $failed) {
            $probe = $this->mock(DependencyProbe::class);
            foreach (DependencyProbe::DEPENDENCIES as $dependency) {
                $probe->shouldReceive('check')->with($dependency)->andReturn($dependency !== $failed);
            }
            $expected = $failed === null ? 'ready' : (in_array($failed, ['database', 'cache']) ? 'unavailable' : 'degraded');
            $response = $this->getJson('/api/v1/health/ready')->assertStatus($failed === null ? 200 : 503);
            $response->assertJsonPath('data.attributes.status', $expected);
            $response->assertJsonCount(6, 'data.attributes.checks');
            if ($failed !== null) {
                $response->assertJsonPath('data.attributes.checks.'.$failed, 'down');
            }
            $this->get('/up')->assertOk();
        }
    }
}
