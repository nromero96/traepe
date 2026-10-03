<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\EventPublisher;
use App\Modules\Platform\Interfaces\Jobs\ConsumeTechnicalEvent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Queue;

final class RedisTechnicalPublisher implements EventPublisher
{
    public function publish(string $eventId, string $correlationId): void
    {
        Context::scope(fn () => Queue::connection('redis')->push(new ConsumeTechnicalEvent($eventId), '', 'technical'),
            ['correlation_id' => $correlationId]);
    }
}
