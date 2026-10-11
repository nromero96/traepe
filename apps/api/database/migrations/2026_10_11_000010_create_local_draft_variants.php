<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION catalog_valid_local_draft_variant_attributes(candidate jsonb) RETURNS boolean
            LANGUAGE sql IMMUTABLE STRICT AS $$
            SELECT CASE WHEN jsonb_typeof(candidate)='object' THEN (
                SELECT count(*)<=20 AND COALESCE(bool_and(
                    key ~ '^[a-z][a-z0-9_]{0,63}$' AND octet_length(key)=char_length(key) AND jsonb_typeof(value)='string'
                    AND char_length(value #>> '{}') BETWEEN 1 AND 255 AND length(btrim(value #>> '{}'))>0
                    AND (value #>> '{}') !~ '[\u0001-\u001F\u007F]'),true)
                FROM jsonb_each(candidate)
            ) ELSE false END
            $$;
            SQL);
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique('catalog_variants_public_unique');
            $table->foreignId('catalog_id')->index('catalog_variants_catalog_index');
            $table->foreignId('product_id');
            $table->char('merchant_public_id', 26);
            $table->string('name', 255);
            $table->string('sku', 64);
            $table->string('size', 255)->nullable();
            $table->string('unit', 64);
            $table->string('barcode', 64)->nullable();
            $table->jsonb('attributes');
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();
            $table->unique(['merchant_public_id', 'sku'], 'catalog_variants_merchant_sku_unique');
            $table->unique(['id', 'product_id'], 'catalog_variants_id_product_unique');
            $table->index(['product_id', 'id'], 'catalog_variants_product_keyset_index');
            $table->foreign(['catalog_id', 'merchant_public_id'], 'catalog_variants_catalog_merchant_fk')->references(['id', 'merchant_public_id'])->on('catalogs')->restrictOnDelete();
            $table->foreign(['product_id', 'catalog_id'], 'catalog_variants_product_catalog_fk')->references(['id', 'catalog_id'])->on('products')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE product_variants ADD CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$'), ADD CHECK (merchant_public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$'), ADD CHECK (sku ~ '^[A-Z0-9][A-Z0-9._-]{0,63}$' AND octet_length(sku)=char_length(sku)), ADD CHECK (status='draft'), ADD CHECK (version=1), ADD CHECK (deleted_at IS NULL OR deleted_at>=created_at), ADD CHECK (catalog_valid_local_draft_variant_attributes(attributes))");
        foreach (['name', 'unit'] as $column) {
            DB::statement("ALTER TABLE product_variants ADD CHECK (length(btrim({$column}))>0 AND {$column} !~ '[\\u0001-\\u001F\\u007F]')");
        }
        DB::statement("ALTER TABLE product_variants ADD CHECK (size IS NULL OR (length(btrim(size))>0 AND size !~ '[\\u0001-\\u001F\\u007F]')), ADD CHECK (barcode IS NULL OR (barcode ~ '^[!-~]{1,64}$' AND octet_length(barcode)=char_length(barcode)))");
        Schema::create('catalog_local_draft_variant_operations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique('catalog_variant_operations_public_unique');
            $table->foreignId('catalog_id');
            $table->foreignId('product_id');
            $table->foreignId('variant_id')->unique('catalog_variant_operations_variant_unique');
            $table->char('merchant_public_id', 26);
            $table->char('actor_public_id', 26);
            $table->smallInteger('schema_version');
            $table->integer('catalog_version_before');
            $table->integer('catalog_version_after');
            $table->integer('product_version_before');
            $table->integer('product_version_after');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('response_hash', 64);
            $table->char('correlation_id', 26);
            $table->text('response_snapshot_ciphertext');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['catalog_id', 'catalog_version_after'], 'catalog_variant_operations_catalog_revision_unique');
            $table->unique(['product_id', 'product_version_after'], 'catalog_variant_operations_product_revision_unique');
            $table->foreign(['catalog_id', 'merchant_public_id'], 'catalog_variant_operations_catalog_merchant_fk')->references(['id', 'merchant_public_id'])->on('catalogs')->restrictOnDelete();
            $table->foreign(['product_id', 'catalog_id'], 'catalog_variant_operations_product_catalog_fk')->references(['id', 'catalog_id'])->on('products')->restrictOnDelete();
            $table->foreign(['variant_id', 'product_id'], 'catalog_variant_operations_variant_product_fk')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete();
        });
        foreach (['public_id', 'merchant_public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_variant_operations ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash', 'response_hash'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_variant_operations ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        DB::statement('ALTER TABLE catalog_local_draft_variant_operations ADD CHECK (schema_version=1), ADD CHECK (catalog_version_before>=1 AND catalog_version_before<2147483647), ADD CHECK (catalog_version_after::bigint=catalog_version_before::bigint+1), ADD CHECK (product_version_before>=1 AND product_version_before<2147483647), ADD CHECK (product_version_after::bigint=product_version_before::bigint+1), ADD CHECK (length(btrim(response_snapshot_ciphertext))>0)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION catalog_reject_local_draft_variant_operation_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Catalog variant operations are append-only' USING ERRCODE='23514'; END;
            $$;
            CREATE TRIGGER catalog_local_draft_variant_operations_append_only BEFORE UPDATE OR DELETE ON catalog_local_draft_variant_operations
            FOR EACH ROW EXECUTE FUNCTION catalog_reject_local_draft_variant_operation_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog variants are forward-only; rollback requires a reviewed data plan.');
    }
};
