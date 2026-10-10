<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_local_fixture_operations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('market_id')->unique()->constrained('markets')->restrictOnDelete();
            $table->char('actor_public_id', 26)->index();
            $table->string('profile_version', 64);
            $table->smallInteger('schema_version');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->char('correlation_id', 26);
            $table->jsonb('response_snapshot');
            $table->timestampTz('created_at')->useCurrent()->index();
        });
        foreach (['public_id', 'actor_public_id', 'correlation_id'] as $column) {
            DB::statement("ALTER TABLE marketplace_local_fixture_operations ADD CHECK ({$column} ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
        }
        foreach (['key_hash', 'request_hash'] as $column) {
            DB::statement("ALTER TABLE marketplace_local_fixture_operations ADD CHECK ({$column} ~ '^[a-f0-9]{64}$')");
        }
        DB::statement("ALTER TABLE marketplace_local_fixture_operations ADD CHECK (profile_version IN ('synthetic-origin-a-v1', 'synthetic-origin-b-v1')), ADD CHECK (schema_version = 1), ADD CHECK (jsonb_typeof(response_snapshot) = 'object')");
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION marketplace_reject_fixture_operation_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Fixture operations are append-only' USING ERRCODE = '23514'; END;
            $$;
            CREATE TRIGGER marketplace_fixture_operations_append_only
            BEFORE UPDATE OR DELETE ON marketplace_local_fixture_operations
            FOR EACH ROW EXECUTE FUNCTION marketplace_reject_fixture_operation_change();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Fixture operations are forward-only; rollback requires a reviewed data plan.');
    }
};
