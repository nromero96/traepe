<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_local_draft_product_changes', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique('catalog_product_changes_public_unique');
            $table->foreignId('catalog_id')->constrained('catalogs')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
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
            $table->unique(['catalog_id', 'catalog_version_after'], 'catalog_product_changes_catalog_revision_unique');
            $table->unique(['product_id', 'product_version_after'], 'catalog_product_changes_product_revision_unique');
            $table->foreign(['catalog_id', 'merchant_public_id'], 'catalog_product_changes_catalog_merchant_fk')->references(['id', 'merchant_public_id'])->on('catalogs')->restrictOnDelete();
            $table->foreign(['product_id', 'catalog_id'], 'catalog_product_changes_product_catalog_fk')->references(['id', 'catalog_id'])->on('products')->restrictOnDelete();
        });
        foreach (['public_id', 'merchant_public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_product_changes ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash', 'response_hash'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_product_changes ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        DB::statement('ALTER TABLE catalog_local_draft_product_changes ADD CHECK (schema_version=1), ADD CHECK (catalog_version_before>=1 AND catalog_version_before<2147483647), ADD CHECK (catalog_version_after::bigint=catalog_version_before::bigint+1), ADD CHECK (product_version_before>=1 AND product_version_before<2147483647), ADD CHECK (product_version_after::bigint=product_version_before::bigint+1), ADD CHECK (length(btrim(response_snapshot_ciphertext))>0)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION catalog_reject_local_draft_product_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Catalog product changes are append-only' USING ERRCODE='23514'; END;
            $$;
            CREATE TRIGGER catalog_local_draft_product_changes_append_only BEFORE UPDATE OR DELETE ON catalog_local_draft_product_changes
            FOR EACH ROW EXECUTE FUNCTION catalog_reject_local_draft_product_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog product changes are forward-only; rollback requires a reviewed data plan.');
    }
};
