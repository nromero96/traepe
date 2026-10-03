<?php

namespace App\Shared\Observability;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ReportException
{
    public function __invoke(Throwable $exception): void
    {
        $request = app()->bound('request') ? app('request') : null;
        $id = Context::get('correlation_id') ?? $request?->attributes->get('correlation_id');
        $id = is_string($id) && Str::isUlid($id) ? $id : (string) Str::ulid();
        $http = $request?->attributes->has('correlation_id') ?? false;
        Context::scope(fn () => Log::error($http ? 'http.failed' : 'job.failed', ['exception' => $exception]), ['correlation_id' => $id]);
        if (! $http) {
            Context::flush();
        }
    }
}
