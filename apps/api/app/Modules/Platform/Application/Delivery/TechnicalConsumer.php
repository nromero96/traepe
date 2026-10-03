<?php

namespace App\Modules\Platform\Application\Delivery;

interface TechnicalConsumer
{
    public function consume(string $eventId): bool;
}
