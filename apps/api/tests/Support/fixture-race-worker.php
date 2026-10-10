<?php

use App\Modules\Marketplace\Application\Fixtures\CreateLocalDraftFixture;
use App\Modules\Marketplace\Application\Fixtures\LocalDraftFixtureStore;
use App\Modules\Marketplace\Domain\Fixtures\FixtureOperation;
use App\Modules\Marketplace\Domain\Fixtures\FixtureProfile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('TRAEPE_TEST_DATABASE');
if (! is_string($database) || ! preg_match('/^traepe_00e_test_[a-f0-9]{16}$/D', $database)) {
    exit(1);
}
$app->detectEnvironment(fn () => 'testing');
config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $database]);
DB::purge('pgsql');
$store = app(LocalDraftFixtureStore::class);
$app->instance(LocalDraftFixtureStore::class, new class($store) implements LocalDraftFixtureStore
{
    public function __construct(private LocalDraftFixtureStore $store) {}

    public function create(FixtureProfile $profile, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): FixtureOperation
    {
        // Hold the claim/country locks long enough for the second independent process to contend.
        $operation = $this->store->create($profile, $actorPublicId, $keyHash, $requestHash, $correlationId);
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): FixtureOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo app(CreateLocalDraftFixture::class)->execute((int) $input['actor_id'], FixtureProfile::A, $input['key'], (string) Str::ulid())->publicId;
} catch (Throwable) {
    fwrite(STDERR, "Fixture race failed; details suppressed.\n");
    exit(1);
}
