<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class TechnicalRealtimeProbe implements ShouldBroadcastNow
{
    public function __construct(public readonly string $probeId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('technical.v1')];
    }

    public function broadcastAs(): string
    {
        return 'technical.probe.v1';
    }

    /** @return array{version: int, probe_id: string} */
    public function broadcastWith(): array
    {
        return ['version' => 1, 'probe_id' => $this->probeId];
    }
}
