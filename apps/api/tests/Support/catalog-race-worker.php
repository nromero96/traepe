<?php

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftCatalog;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogInput;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogOperation;
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
$store = app(LocalDraftCatalogStore::class);
$app->instance(LocalDraftCatalogStore::class, new class($store) implements LocalDraftCatalogStore
{
    public function __construct(private LocalDraftCatalogStore $store) {}

    public function create(DraftCatalogInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCatalogOperation
    {
        // Keep the transaction open so the second process contends on the claim/merchant.
        $operation = $this->store->create($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftCatalogOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo app(CreateLocalDraftCatalog::class)->execute((int) $input['actor_id'], DraftCatalogInput::fromArray($input['payload']), $input['key'], (string) Str::ulid())->publicId;
} catch (Throwable) {
    fwrite(STDERR, "Catalog race failed; details suppressed.\n");
    exit(1);
}
