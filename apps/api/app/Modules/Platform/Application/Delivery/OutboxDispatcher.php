<?php

namespace App\Modules\Platform\Application\Delivery;

interface OutboxDispatcher
{
    public function dispatch(int $limit = 100): int;
}
