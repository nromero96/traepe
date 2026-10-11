<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->char('merchant_public_id', 26)->index();
            $table->foreign('merchant_public_id')->references('public_id')->on('merchants')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();
            $table->unique(['id', 'merchant_public_id']);
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('catalog_id')->index()->constrained('catalogs')->restrictOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('brand', 255)->nullable();
            $table->string('product_type', 40)->nullable();
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->softDeletesTz();
            $table->unique(['id', 'catalog_id']);
        });
        foreach (['catalogs', 'products'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$'), ADD CHECK (status = 'draft'), ADD CHECK (version >= 1), ADD CHECK (deleted_at IS NULL OR deleted_at >= created_at)");
            DB::statement("ALTER TABLE {$table} ADD CHECK (length(btrim(name)) > 0 AND name !~ '[\\u0001-\\u001F\\u007F]')");
        }
        DB::statement("ALTER TABLE catalogs ADD CHECK (merchant_public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        DB::statement("ALTER TABLE products ADD CHECK (product_type IS NULL), ADD CHECK (description IS NULL OR (char_length(description) <= 4000 AND description !~ '[\\u0001-\\u0009\\u000B-\\u001F\\u007F]')), ADD CHECK (brand IS NULL OR (length(btrim(brand)) > 0 AND brand !~ '[\\u0001-\\u001F\\u007F]'))");
        Schema::create('catalog_local_draft_operations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('catalog_id')->unique()->constrained('catalogs')->restrictOnDelete();
            $table->foreignId('product_id')->unique()->constrained('products')->restrictOnDelete();
            $table->char('merchant_public_id', 26)->index();
            $table->char('actor_public_id', 26)->index();
            $table->smallInteger('schema_version');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('response_hash', 64);
            $table->char('correlation_id', 26);
            $table->text('response_snapshot_ciphertext');
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->foreign(['catalog_id', 'merchant_public_id'])->references(['id', 'merchant_public_id'])->on('catalogs')->restrictOnDelete();
            $table->foreign(['product_id', 'catalog_id'])->references(['id', 'catalog_id'])->on('products')->restrictOnDelete();
        });
        foreach (['public_id', 'merchant_public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_operations ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash', 'response_hash'] as $column) {
            DB::statement("ALTER TABLE catalog_local_draft_operations ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        DB::statement('ALTER TABLE catalog_local_draft_operations ADD CHECK (schema_version = 1), ADD CHECK (length(btrim(response_snapshot_ciphertext)) > 0)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION catalog_reject_local_draft_operation_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Catalog operations are append-only' USING ERRCODE = '23514'; END;
            $$;
            CREATE TRIGGER catalog_local_draft_operations_append_only BEFORE UPDATE OR DELETE ON catalog_local_draft_operations
            FOR EACH ROW EXECUTE FUNCTION catalog_reject_local_draft_operation_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog foundation is forward-only; rollback requires a reviewed data plan.');
    }
};
