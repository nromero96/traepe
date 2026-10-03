<?php

namespace App\Modules\Platform\Infrastructure\Delivery;

use App\Modules\Platform\Application\Delivery\EventPublisher;
use App\Modules\Platform\Application\Delivery\OutboxDispatcher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final readonly class PostgresOutboxDispatcher implements OutboxDispatcher
{
    public function __construct(private EventPublisher $publisher) {}

    public function dispatch(int $limit = 100): int
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Dispatch batch must be between 1 and 1000.');
        }

        return DB::transaction(function () use ($limit): int {
            $rows = DB::table('platform_outbox_messages')->where('status', 'pending')->whereRaw('next_attempt_at <= CURRENT_TIMESTAMP')
                ->orderBy('id')->limit($limit)->lock('FOR UPDATE SKIP LOCKED')->get();
            $published = 0;
            foreach ($rows as $row) {
                try {
                    // Redis publication occurs before marking published; crashes may redeliver.
                    $this->publisher->publish($row->event_id, $row->correlation_id);
                    DB::table('platform_outbox_messages')->where('id', $row->id)->update([
                        'status' => 'published', 'published_at' => now(), 'attempts' => $row->attempts + 1, 'last_error_code' => null,
                    ]);
                    Context::scope(fn () => Log::info('outbox.published', ['event_id' => $row->event_id]), ['correlation_id' => $row->correlation_id]);
                    $published++;
                } catch (Throwable) {
                    DB::table('platform_outbox_messages')->where('id', $row->id)->update([
                        'attempts' => $row->attempts + 1, 'last_error_code' => 'publication_failed',
                        'next_attempt_at' => now()->addSeconds(min(60, 2 ** min($row->attempts, 6))),
                    ]);
                    Context::scope(fn () => Log::warning('outbox.retry', ['event_id' => $row->event_id]), ['correlation_id' => $row->correlation_id]);
                }
            }

            return $published;
        });
    }
}
