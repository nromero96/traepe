<?php

namespace App\Modules\Platform\Infrastructure;

use App\Modules\Platform\Application\Delivery\EventPublisher;
use App\Modules\Platform\Application\Delivery\IdempotencyStore;
use App\Modules\Platform\Application\Delivery\OutboxDispatcher;
use App\Modules\Platform\Application\Delivery\OutboxDispatchRequest;
use App\Modules\Platform\Application\Delivery\TechnicalConsumer;
use App\Modules\Platform\Application\Delivery\TechnicalProbeStore;
use App\Modules\Platform\Application\Health\DependencyProbe;
use App\Modules\Platform\Application\Scaffolding\ModuleGenerator;
use App\Modules\Platform\Infrastructure\Delivery\PostgresIdempotencyStore;
use App\Modules\Platform\Infrastructure\Delivery\PostgresOutboxDispatcher;
use App\Modules\Platform\Infrastructure\Delivery\PostgresTechnicalConsumer;
use App\Modules\Platform\Infrastructure\Delivery\PostgresTechnicalProbeStore;
use App\Modules\Platform\Infrastructure\Delivery\RedisOutboxDispatchRequest;
use App\Modules\Platform\Infrastructure\Delivery\RedisTechnicalPublisher;
use App\Modules\Platform\Infrastructure\Scaffolding\ModuleScaffolder;
use App\Modules\Platform\Interfaces\Console\DispatchTechnicalOutbox;
use App\Modules\Platform\Interfaces\Console\MakeModule;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DependencyProbe::class, Health\DependencyHealth::class);
        $this->app->bind(IdempotencyStore::class, PostgresIdempotencyStore::class);
        $this->app->bind(TechnicalProbeStore::class, PostgresTechnicalProbeStore::class);
        $this->app->bind(EventPublisher::class, RedisTechnicalPublisher::class);
        $this->app->bind(OutboxDispatcher::class, PostgresOutboxDispatcher::class);
        $this->app->bind(OutboxDispatchRequest::class, RedisOutboxDispatchRequest::class);
        $this->app->bind(TechnicalConsumer::class, PostgresTechnicalConsumer::class);
        $this->app->singleton(ModuleGenerator::class, fn () => new ModuleScaffolder(app_path('Modules')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeModule::class, DispatchTechnicalOutbox::class]);
        }
        Context::dehydrating(function ($context): void {
            $id = $context->get('correlation_id');
            $context->flush();
            if (is_string($id) && Str::isUlid($id)) {
                $context->add('correlation_id', $id);
            }
        });
        Context::hydrated(function ($context): void {
            $id = $context->get('correlation_id');
            $context->flush()->add('correlation_id', is_string($id) && Str::isUlid($id) ? $id : (string) Str::ulid());
        });
        Queue::after(function (JobProcessed $event): void {
            Log::info('job.processed', ['attempt' => $event->job->attempts()]);
            if ($event->connectionName !== 'sync') {
                Context::flush();
            }
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            Log::error('job.failed', ['attempt' => $event->job->attempts(), 'exception' => $event->exception]);
        });
    }
}
