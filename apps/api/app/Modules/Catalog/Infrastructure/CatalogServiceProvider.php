<?php

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftCatalogWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftMerchantSource;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductChangeWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductCursorCodec;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftProductWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeAccess;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantChangeWriter;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantCursorCodec;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantStore;
use App\Modules\Catalog\Application\Drafts\LocalDraftVariantWriter;
use App\Modules\Catalog\Infrastructure\Drafts\CryptLocalDraftProductCursorCodec;
use App\Modules\Catalog\Infrastructure\Drafts\CryptLocalDraftVariantCursorCodec;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftCatalogAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftProductAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftProductChangeAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftVariantAccess;
use App\Modules\Catalog\Infrastructure\Drafts\IdentityLocalDraftVariantChangeAccess;
use App\Modules\Catalog\Infrastructure\Drafts\MarketplaceLocalDraftMerchantSource;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftCatalogWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftProductChangeWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftProductWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftVariantChangeWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PlatformLocalDraftVariantWriter;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftCatalogStore;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftProductChangeStore;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftProductStore;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftVariantChangeStore;
use App\Modules\Catalog\Infrastructure\Drafts\PostgresLocalDraftVariantStore;
use App\Modules\Catalog\Interfaces\Http\LocalCatalogEnvironment;
use App\Modules\Catalog\Interfaces\Http\LocalDraftCatalogController;
use App\Modules\Catalog\Interfaces\Http\LocalDraftProductChangeController;
use App\Modules\Catalog\Interfaces\Http\LocalDraftProductController;
use App\Modules\Catalog\Interfaces\Http\LocalDraftVariantChangeController;
use App\Modules\Catalog\Interfaces\Http\LocalDraftVariantController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            $this->app->bind(LocalDraftVariantChangeAccess::class, IdentityLocalDraftVariantChangeAccess::class);
            $this->app->bind(LocalDraftVariantChangeStore::class, PostgresLocalDraftVariantChangeStore::class);
            $this->app->bind(LocalDraftVariantChangeWriter::class, PlatformLocalDraftVariantChangeWriter::class);
            $this->app->bind(LocalDraftVariantAccess::class, IdentityLocalDraftVariantAccess::class);
            $this->app->bind(LocalDraftVariantStore::class, PostgresLocalDraftVariantStore::class);
            $this->app->bind(LocalDraftVariantWriter::class, PlatformLocalDraftVariantWriter::class);
            $this->app->bind(LocalDraftVariantCursorCodec::class, CryptLocalDraftVariantCursorCodec::class);
            $this->app->bind(LocalDraftProductChangeAccess::class, IdentityLocalDraftProductChangeAccess::class);
            $this->app->bind(LocalDraftProductChangeStore::class, PostgresLocalDraftProductChangeStore::class);
            $this->app->bind(LocalDraftProductChangeWriter::class, PlatformLocalDraftProductChangeWriter::class);
            $this->app->bind(LocalDraftProductAccess::class, IdentityLocalDraftProductAccess::class);
            $this->app->bind(LocalDraftProductStore::class, PostgresLocalDraftProductStore::class);
            $this->app->bind(LocalDraftProductWriter::class, PlatformLocalDraftProductWriter::class);
            $this->app->bind(LocalDraftProductCursorCodec::class, CryptLocalDraftProductCursorCodec::class);
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
        Route::put('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants/{variantPublicId}', [LocalDraftVariantChangeController::class, 'update'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-variant-update:']);
        Route::get('api/v1/catalog/local-draft-variant-change-operations/{operationPublicId}', [LocalDraftVariantChangeController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-variant-change-read:']);
        Route::post('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants', [LocalDraftVariantController::class, 'create'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-variant-create:']);
        Route::get('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}/variants', [LocalDraftVariantController::class, 'variants'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-variant-content:']);
        Route::get('api/v1/catalog/local-draft-variant-operations/{operationPublicId}', [LocalDraftVariantController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-variant-read:']);
        Route::put('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products/{productPublicId}', [LocalDraftProductChangeController::class, 'update'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-product-update:']);
        Route::get('api/v1/catalog/local-draft-product-change-operations/{operationPublicId}', [LocalDraftProductChangeController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-product-change-read:']);
        Route::post('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products', [LocalDraftProductController::class, 'create'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-product-create:']);
        Route::get('api/v1/catalog/local-draft-profiles/{catalogPublicId}', [LocalDraftProductController::class, 'profile'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-content-read:']);
        Route::get('api/v1/catalog/local-draft-profiles/{catalogPublicId}/products', [LocalDraftProductController::class, 'products'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-content-read:']);
        Route::get('api/v1/catalog/local-draft-product-operations/{operationPublicId}', [LocalDraftProductController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-product-read:']);
        Route::post('api/v1/catalog/local-draft-catalogs', [LocalDraftCatalogController::class, 'create'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-catalog-create:']);
        Route::get('api/v1/catalog/local-draft-catalogs/{operationPublicId}', [LocalDraftCatalogController::class, 'read'])
            ->middleware([LocalCatalogEnvironment::class, 'web', 'auth:web', 'throttle:30,1,local-draft-catalog-read:']);
    }
}
