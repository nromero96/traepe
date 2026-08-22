<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class TechnicalRedisProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $key)
    {
        $this->onConnection('redis');
        $this->onQueue('technical');
    }

    public function handle(): void
    {
        Cache::store('redis')->put($this->key, 'processed', now()->addMinutes(5));
    }
}
