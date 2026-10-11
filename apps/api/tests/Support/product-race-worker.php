<?php

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogProfile;
use App\Modules\Catalog\Domain\Drafts\DraftProductCursor;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductOperation;
use App\Modules\Catalog\Domain\Drafts\DraftProductPage;
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
$store = app(LocalDraftProductStore::class);
$app->instance(LocalDraftProductStore::class, new class($store) implements LocalDraftProductStore
{
    public function __construct(private LocalDraftProductStore $store) {}

    public function profile(string $catalogPublicId, string $actorPublicId): DraftCatalogProfile
    {
        return $this->store->profile($catalogPublicId, $actorPublicId);
    }

    public function page(string $catalogPublicId, string $actorPublicId, int $limit, ?DraftProductCursor $cursor): DraftProductPage
    {
        return $this->store->page($catalogPublicId, $actorPublicId, $limit, $cursor);
    }

    public function append(DraftProductInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftProductOperation
    {
        $operation = $this->store->append($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        // Retain claim/merchant/catalog locks so both workers contend on an open transaction.
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftProductOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
$value = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $value['start']) {
    usleep(10000);
}
try {
    $op = app(CreateLocalDraftProduct::class)->execute($value['actor_id'], new DraftProductInput($value['catalog_public_id'], $value['version'], new DraftProductData(' Synthetic Additional Canary ', "Synthetic Description Canary\nliteral", null)), $value['key'], (string) Str::ulid());
    echo json_encode(['code' => 'created', 'operation' => $op->publicId], JSON_THROW_ON_ERROR);
} catch (DraftCatalogFailure $e) {
    echo json_encode(['code' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Product worker failed.\n");
    exit(1);
}
