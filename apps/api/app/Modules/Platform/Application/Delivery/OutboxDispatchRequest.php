<?php

namespace App\Modules\Platform\Application\Delivery;

interface OutboxDispatchRequest
{
    public function enqueue(int $limit): void;
}
