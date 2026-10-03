<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TechnicalHorizonProbe extends TechnicalRedisProbe
{
    public function __construct(string $key, public readonly bool $failOnce = false, public int $tries = 3)
    {
        parent::__construct($key);
    }

    public function handle(): void
    {
        if ($this->failOnce && Cache::store('redis')->add($this->key.':first-attempt', true, 300)) {
            throw new RuntimeException('Controlled technical probe failure.');
        }

        // Retrying or redelivering this probe produces the same observable value.
        parent::handle();
    }
}
