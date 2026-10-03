<?php

namespace App\Modules\Platform\Application\Health;

final readonly class Readiness
{
    public function __construct(private DependencyProbe $probe) {}

    /** @return array{status: 'ready'|'unavailable'|'degraded', checks: array<string, 'up'|'down'>} */
    public function check(): array
    {
        $checks = [];
        foreach (DependencyProbe::DEPENDENCIES as $dependency) {
            $checks[$dependency] = $this->probe->check($dependency) ? 'up' : 'down';
        }
        $criticalDown = $checks['database'] === 'down' || $checks['cache'] === 'down';

        return [
            'status' => ! in_array('down', $checks, true) ? 'ready' : ($criticalDown ? 'unavailable' : 'degraded'),
            'checks' => $checks,
        ];
    }
}
