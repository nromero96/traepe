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
            DO $$ BEGIN
                IF (SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid='product_variants'::regclass AND conname='product_variants_version_check')
                    IS DISTINCT FROM 'CHECK ((version = 1))' THEN
                    RAISE EXCEPTION 'Unexpected draft variant revision constraint' USING ERRCODE='23514';
                END IF;
            END $$;
            ALTER TABLE product_variants DROP CONSTRAINT product_variants_version_check,
                ADD CONSTRAINT product_variants_version_check CHECK (version>=1);
            SQL);
        Schema::create('catalog_local_draft_variant_changes', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique('catalog_variant_changes_public_unique');
            $table->foreignId('catalog_id');
            $table->foreignId('product_id');
            $table->foreignId('variant_id');
            $table->char('merchant_public_id', 26);
            $table->char('actor_public_id', 26);
            $table->smallInteger('schema_version');
            foreach (['catalog', 'product', 'variant'] as $profile) {
                $table->integer($profile.'_version_before');
                $table->integer($profile.'_version_after');
                $table->unique([$profile.'_id', $profile.'_version_after'], 'catalog_variant_changes_'.$profile.'_revision_unique');
            }
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('response_hash', 64);
            $table->char('correlation_id', 26);
            $table->text('response_snapshot_ciphertext');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign(['catalog_id', 'merchant_public_id'], 'catalog_variant_changes_catalog_merchant_fk')->references(['id', 'merchant_public_id'])->on('catalogs')->restrictOnDelete();
            $table->foreign(['product_id', 'catalog_id'], 'catalog_variant_changes_product_catalog_fk')->references(['id', 'catalog_id'])->on('products')->restrictOnDelete();
            $table->foreign(['variant_id', 'product_id'], 'catalog_variant_changes_variant_product_fk')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete();
        });
        foreach (['public_id', 'merchant_public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_variant_changes ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash', 'response_hash'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_variant_changes ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        foreach (['catalog', 'product', 'variant'] as $profile) {
            DB::statement("ALTER TABLE catalog_local_draft_variant_changes ADD CHECK ({$profile}_version_before>=1 AND {$profile}_version_before<2147483647), ADD CHECK ({$profile}_version_after::bigint={$profile}_version_before::bigint+1)");
        }
        DB::statement('ALTER TABLE catalog_local_draft_variant_changes ADD CHECK (schema_version=1), ADD CHECK (length(btrim(response_snapshot_ciphertext))>0)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION catalog_reject_local_draft_variant_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Catalog variant changes are append-only' USING ERRCODE='23514'; END;
            $$;
            CREATE TRIGGER catalog_local_draft_variant_changes_append_only BEFORE UPDATE OR DELETE ON catalog_local_draft_variant_changes
            FOR EACH ROW EXECUTE FUNCTION catalog_reject_local_draft_variant_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog variant changes are forward-only; rollback requires a reviewed data plan.');
    }
};
