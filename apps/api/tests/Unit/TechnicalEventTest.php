<?php

namespace Tests\Unit;

use App\Modules\Platform\Domain\Delivery\TechnicalEvent;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TechnicalEventTest extends TestCase
{
    private function envelope(): array
    {
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        return [
            'event_id' => $id, 'event_name' => TechnicalEvent::NAME, 'version' => 1,
            'aggregate' => ['type' => 'technical_probe', 'public_id' => $id],
            'occurred_at' => '2026-10-03T12:00:00+00:00', 'recorded_at' => '2026-10-03T12:00:00+00:00',
            'actor' => ['type' => 'system', 'public_id' => null], 'correlation_id' => $id,
            'causation_id' => null, 'idempotency_key_hash' => str_repeat('a', 64),
            'context' => ['market_id' => null, 'branch_id' => null],
            'payload' => ['operation_id' => $id], 'metadata' => ['schema_version' => 1],
        ];
    }

    public function test_versioned_envelope_is_pure_and_key_order_is_not_significant(): void
    {
        $envelope = $this->envelope();
        $envelope['actor'] = array_reverse($envelope['actor']);
        $this->assertSame(TechnicalEvent::NAME, (new TechnicalEvent($envelope))->envelope['event_name']);
        foreach ([['version' => 2], ['correlation_id' => 'invalid'], ['occurred_at' => '2026-10-03T12:00:00-05:00'],
            ['payload' => ['operation_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX']], ['actor' => ['type' => 'system', 'public_id' => false]], ['unexpected' => 'value']] as $change) {
            try {
                new TechnicalEvent(array_replace($envelope, $change));
                $this->fail('Invalid envelope was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('technical_event_invalid', $exception->getMessage());
            }
        }
    }

    public function test_event_content_cannot_be_changed_in_memory(): void
    {
        $event = new TechnicalEvent($this->envelope());
        $this->expectException(Error::class);
        $event->envelope['version'] = 2;
    }
}
