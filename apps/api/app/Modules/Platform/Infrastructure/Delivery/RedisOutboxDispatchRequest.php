<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\OutboxDispatchRequest;
use App\Modules\Platform\Interfaces\Jobs\PublishTechnicalOutbox;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class RedisOutboxDispatchRequest implements OutboxDispatchRequest
{
    public function enqueue(int $limit): void
    {
        $id = Context::get('correlation_id');
        $id = is_string($id) && Str::isUlid($id) ? $id : (string) Str::ulid();
        Context::scope(fn () => Queue::connection('redis')->push(new PublishTechnicalOutbox($limit), '', 'technical'), ['correlation_id' => $id]);
    }
}
