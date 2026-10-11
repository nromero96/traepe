<?php

use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftProduct;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('TRAEPE_TEST_DATABASE');
if (! is_string($database) || ! preg_match('/^traepe_00e_test_[a-f0-9]{16}$/D', $database)) {
    exit(2);
}
config(['database.connections.pgsql.database' => $database]);
DB::purge('pgsql');
$app->detectEnvironment(fn () => 'testing');
$store = app(LocalDraftProductChangeStore::class);
$app->instance(LocalDraftProductChangeStore::class, new class($store) implements LocalDraftProductChangeStore
{
    public function __construct(private LocalDraftProductChangeStore $store) {}

    public function change(DraftProductChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductChangeOperation
    {
        $operation = $this->store->change($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        // Keep the real transaction open while the competing worker reaches its locks.
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftProductChangeOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
$value = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $value['start']) {
    usleep(10000);
}
try {
    $op = app(UpdateLocalDraftProduct::class)->execute($value['actor_id'], new DraftProductChangeInput($value['catalog_public_id'], $value['product_public_id'], $value['version'], new DraftProductData($value['name'], '', null)), $value['key'], (string) Str::ulid());
    echo json_encode(['code' => 'changed', 'operation' => $op->publicId], JSON_THROW_ON_ERROR);
} catch (DraftCatalogFailure $e) {
    echo json_encode(['code' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Change worker failed.\n");
    exit(1);
}
