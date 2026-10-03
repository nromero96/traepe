<?php

namespace App\Modules\Platform\Application\Delivery;

interface EventPublisher
{
    public function publish(string $eventId, string $correlationId): void;
}
