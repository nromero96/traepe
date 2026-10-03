<?php

namespace Tests\Feature;

use App\Modules\Platform\Application\Delivery\OutboxDispatchRequest;
use Tests\TestCase;

class OutboxCommandTest extends TestCase
{
    public function test_batch_command_validates_limits_before_enqueueing(): void
    {
        $request = $this->mock(OutboxDispatchRequest::class);
        $request->shouldReceive('enqueue')->once()->with(100);
        $this->artisan('platform:dispatch-outbox')->assertSuccessful();
        foreach (['0', '1001', 'invalid'] as $limit) {
            $this->artisan('platform:dispatch-outbox', ['--limit' => $limit])->assertFailed();
        }
    }
}
