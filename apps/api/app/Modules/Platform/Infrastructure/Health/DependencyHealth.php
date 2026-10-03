<?php

namespace App\Modules\Platform\Infrastructure\Health;

use App\Modules\Platform\Application\Health\DependencyProbe;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class DependencyHealth implements DependencyProbe
{
    public function check(string $dependency): bool
    {
        try {
            return match ($dependency) {
                'database' => DB::selectOne('SELECT 1 AS healthy')->healthy === 1,
                'cache' => (bool) Redis::connection()->ping(),
                'storage' => $this->storageReady(),
                'queue' => collect(app(MasterSupervisorRepository::class)->all())
                    ->contains(fn ($supervisor) => $supervisor->status === 'running'),
                'realtime' => ReverbHealth::check('reverb', 8080, (string) config('broadcasting.connections.reverb.key')),
                'mail' => Http::connectTimeout(1)->timeout(2)->get('http://mailpit:8025/readyz')->successful(),
                default => false,
            };
        } catch (Throwable) {
            // Never log connection exceptions: they may contain credentials or internal hosts.
            return false;
        }
    }

    private function storageReady(): bool
    {
        $disk = Storage::disk('s3');

        if (! $disk instanceof AwsS3V3Adapter) {
            return false;
        }
        $disk->getClient()->headBucket([
            'Bucket' => config('filesystems.disks.s3.bucket'),
        ]);

        return true;
    }
}
