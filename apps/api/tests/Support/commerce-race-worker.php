<?php

use App\Modules\Marketplace\Application\Commerce\CreateLocalDraftCommerce;
use App\Modules\Marketplace\Application\Commerce\LocalDraftCommerceStore;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceInput;
use App\Modules\Marketplace\Domain\Commerce\DraftCommerceOperation;
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
$store = app(LocalDraftCommerceStore::class);
$app->instance(LocalDraftCommerceStore::class, new class($store) implements LocalDraftCommerceStore
{
    public function __construct(private LocalDraftCommerceStore $store) {}

    public function create(DraftCommerceInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftCommerceOperation
    {
        // Keep the transaction open so the second process contends on the same claim/market.
        $operation = $this->store->create($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftCommerceOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
try {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    echo app(CreateLocalDraftCommerce::class)->execute((int) $input['actor_id'], DraftCommerceInput::fromArray($input['payload']), $input['key'], (string) Str::ulid())->publicId;
} catch (Throwable) {
    fwrite(STDERR, "Commerce race failed; details suppressed.\n");
    exit(1);
}
