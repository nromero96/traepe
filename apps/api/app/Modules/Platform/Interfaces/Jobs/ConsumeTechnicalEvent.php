<?php

namespace App\Modules\Platform\Interfaces\Jobs;

use App\Modules\Platform\Application\Delivery\TechnicalConsumer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ConsumeTechnicalEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5, 10];

    public function __construct(public readonly string $eventId) {}

    public function handle(TechnicalConsumer $consumer): void
    {
        $consumer->consume($this->eventId);
    }
}
