<?php

namespace App\Modules\Platform\Application\Delivery;

use DateTimeImmutable;

final readonly class TechnicalProbe
{
    public function __construct(private IdempotencyStore $idempotency, private TechnicalProbeStore $store) {}

    /** @param array<array-key, mixed> $payload */
    public function create(string $key, string $correlationId, DateTimeImmutable $expiresAt, array $payload = []): string
    {
        return $this->idempotency->execute('platform.technical_probe.v1', 'technical-system', $key, $payload, $expiresAt,
            fn () => $this->store->create($correlationId, hash('sha256', $key)));
    }
}
