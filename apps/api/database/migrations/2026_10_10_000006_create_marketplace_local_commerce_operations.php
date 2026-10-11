<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_local_commerce_operations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('merchant_id')->unique()->constrained('merchants')->restrictOnDelete();
            $table->foreignId('branch_id')->unique()->constrained('branches')->restrictOnDelete();
            $table->foreignId('market_id')->index()->constrained('markets')->restrictOnDelete();
            $table->char('actor_public_id', 26)->index();
            $table->smallInteger('schema_version');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('response_hash', 64);
            $table->char('correlation_id', 26);
            $table->text('response_snapshot_ciphertext');
            $table->timestampTz('created_at')->useCurrent()->index();
        });
        foreach (['public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE marketplace_local_commerce_operations ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash', 'response_hash'] as $column) {
            DB::statement("ALTER TABLE marketplace_local_commerce_operations ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        DB::statement('ALTER TABLE marketplace_local_commerce_operations ADD CHECK (schema_version = 1), ADD CHECK (length(btrim(response_snapshot_ciphertext)) > 0)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION marketplace_reject_commerce_operation_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Commerce operations are append-only' USING ERRCODE = '23514'; END;
            $$;
            CREATE TRIGGER marketplace_commerce_operations_append_only
            BEFORE UPDATE OR DELETE ON marketplace_local_commerce_operations
            FOR EACH ROW EXECUTE FUNCTION marketplace_reject_commerce_operation_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Commerce operations are forward-only; rollback requires a reviewed data plan.');
    }
};
