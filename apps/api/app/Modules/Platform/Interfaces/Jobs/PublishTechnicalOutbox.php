<?php

namespace App\Modules\Platform\Interfaces\Jobs;

use App\Modules\Platform\Application\Delivery\OutboxDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PublishTechnicalOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5, 10];

    public function __construct(public readonly int $limit = 100) {}

    public function handle(OutboxDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->limit);
    }
}
