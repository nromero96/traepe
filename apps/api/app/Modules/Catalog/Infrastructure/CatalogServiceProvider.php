<?php

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftCatalogAccess;
use App\Modules\Catalog\Infrastructure\Drafts\MarketplaceLocalDraftMerchantSource;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftCatalogWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftCatalogStore;
use App\Modules\Catalog\Interfaces\Http\LocalCatalogEnvironment;
use App\Modules\Catalog\Interfaces\Http\LocalDraftCatalogController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->bind(LocalDraftCatalogAccess::class, IdentityLocalDraftCatalogAccess::class);
            $this->app->bind(LocalDraftCatalogStore::class, PostgresLocalDraftCatalogStore::class);
            $this->app->bind(LocalDraftCatalogWriter::class, PlatformLocalDraftCatalogWriter::class);
            $this->app->bind(LocalDraftMerchantSource::class, MarketplaceLocalDraftMerchantSource::class);
        }
    }

    public function boot(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            return;
        }
        Route::post('api/v1/catalog/local-draft-catalogs', [LocalDraftCatalogController::class, 'create'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-catalog-create:']);
        Route::get('api/v1/catalog/local-draft-catalogs/{operationPublicId}', [LocalDraftCatalogController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-catalog-read:']);
    }
}
