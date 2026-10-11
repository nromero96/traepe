<?php

use App\Modules\Catalog\Application\Drafts\CreateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\CreateLocalDraftVariant;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftProduct;
use App\Modules\Catalog\Application\Drafts\UpdateLocalDraftVariant;
use App\Modules\Catalog\Domain\Drafts\DraftCatalogFailure;
use App\Modules\Catalog\Domain\Drafts\DraftProductChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftProductData;
use App\Modules\Catalog\Domain\Drafts\DraftProductInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantChangeOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantCursor;
use App\Modules\Catalog\Domain\Drafts\DraftVariantData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantEditableData;
use App\Modules\Catalog\Domain\Drafts\DraftVariantInput;
use App\Modules\Catalog\Domain\Drafts\DraftVariantOperation;
use App\Modules\Catalog\Domain\Drafts\DraftVariantPage;
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
$store = app(LocalDraftVariantStore::class);
$app->instance(LocalDraftVariantStore::class, new class($store) implements LocalDraftVariantStore
{
    public function __construct(private LocalDraftVariantStore $store) {}

    public function append(DraftVariantInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantOperation
    {
        $operation = $this->store->append($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        // Hold the real PostgreSQL locks while the competing process reaches them.
        usleep(500000);

        return $operation;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }

    public function page(string $catalogPublicId, string $productPublicId, string $actorPublicId, int $limit, ?DraftVariantCursor $cursor): DraftVariantPage
    {
        return $this->store->page($catalogPublicId, $productPublicId, $actorPublicId, $limit, $cursor);
    }
});
$changes = app(LocalDraftVariantChangeStore::class);
$app->instance(LocalDraftVariantChangeStore::class, new class($changes) implements LocalDraftVariantChangeStore
{
    public function __construct(private LocalDraftVariantChangeStore $store) {}

    public function change(DraftVariantChangeInput $input, string $actorPublicId, string $keyHash, string $requestHash, string $correlationId): DraftVariantChangeOperation
    {
        $op = $this->store->change($input, $actorPublicId, $keyHash, $requestHash, $correlationId);
        usleep(500000);

        return $op;
    }

    public function find(string $operationPublicId, string $actorPublicId): DraftVariantChangeOperation
    {
        return $this->store->find($operationPublicId, $actorPublicId);
    }
});
$value = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $value['start']) {
    usleep(10000);
}
try {
    if ($value['mode'] === 'variant_change') {
        $op = app(UpdateLocalDraftVariant::class)->execute($value['actor_id'], new DraftVariantChangeInput($value['catalog_public_id'], $value['product_public_id'], $value['variant_public_id'], $value['version'],
            new DraftVariantEditableData($value['name'], null, 'unit', null, [])), $value['key'], (string) Str::ulid());
        $code = 'variant_changed';
    } elseif ($value['mode'] === 'append') {
        $op = app(CreateLocalDraftProduct::class)->execute($value['actor_id'], new DraftProductInput($value['catalog_public_id'], $value['version'], new DraftProductData($value['name'], null, null)), $value['key'], (string) Str::ulid());
        $code = 'added';
    } elseif ($value['mode'] === 'change') {
        $op = app(UpdateLocalDraftProduct::class)->execute($value['actor_id'], new DraftProductChangeInput($value['catalog_public_id'], $value['product_public_id'], $value['version'], new DraftProductData($value['name'], null, null)), $value['key'], (string) Str::ulid());
        $code = 'changed';
    } else {
        $op = app(CreateLocalDraftVariant::class)->execute($value['actor_id'], new DraftVariantInput($value['catalog_public_id'], $value['product_public_id'], $value['version'], new DraftVariantData($value['name'], $value['sku'], null, 'unit', null, [])), $value['key'], (string) Str::ulid());
        $code = 'created';
    }
    echo json_encode(['code' => $code, 'operation' => $op->publicId], JSON_THROW_ON_ERROR);
} catch (DraftCatalogFailure $e) {
    echo json_encode(['code' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Variant worker failed.\n");
    exit(1);
}
