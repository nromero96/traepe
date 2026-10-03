<?php

namespace App\Modules\Platform\Application\Delivery;

interface TechnicalProbeStore
{
    public function create(string $correlationId, string $keyHash): string;
}
