<?php

namespace App\Shared\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Throwable;

final class SafeLogProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $safe = [];
        foreach (['correlation_id', 'event_id', 'operation_id'] as $key) {
            $value = $key === 'correlation_id' ? Context::get($key) : ($record->context[$key] ?? null);
            if (is_string($value) && Str::isUlid($value)) {
                $safe[$key] = $value;
            }
        }
        foreach (['status_code', 'duration_ms', 'attempt'] as $key) {
            if (isset($record->context[$key]) && is_int($record->context[$key]) && $record->context[$key] >= 0) {
                $safe[$key] = $record->context[$key];
            }
        }
        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof Throwable) {
            $type = get_class($exception);
            $safe['exception_type'] = preg_match('/\A[A-Za-z_][A-Za-z0-9_\\\\]*\z/', $type) ? $type : 'Throwable';
        }
        // Closed vocabulary: even a free-text message can contain PII/secrets.
        $message = in_array($record->message, [
            'http.completed', 'http.failed', 'job.processed', 'job.failed',
            'technical.probe', 'outbox.published', 'outbox.retry', 'inbox.consumed',
        ], true) ? $record->message : 'technical.redacted';

        return $record->with(message: $message, context: $safe, extra: []);
    }
}
