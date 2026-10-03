<?php

namespace App\Modules\Platform\Application\Delivery;

use Closure;
use DateTimeImmutable;

interface IdempotencyStore
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @param  Closure(): mixed  $change
     */
    public function execute(string $scope, string $actor, string $key, array $payload, DateTimeImmutable $expiresAt, Closure $change): string;
}
