<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->char('public_id', 26)->nullable()->unique();
            $table->char('phone_key', 64)->nullable()->unique();
            $table->text('phone_ciphertext')->nullable();
            $table->string('status', 16)->default('active');
        });
        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT identity_status_check CHECK (status IN ('active', 'blocked'))");
        Schema::create('identity_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users');
            $table->text('name_ciphertext');
        });
        Schema::create('identity_otp_challenges', function (Blueprint $table): void {
            $table->char('public_id', 26)->primary();
            $table->char('phone_key', 64)->unique();
            $table->text('phone_ciphertext');
            $table->text('code_ciphertext');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
        });
        DB::statement('ALTER TABLE identity_otp_challenges ADD CONSTRAINT identity_attempts_check CHECK (attempts BETWEEN 0 AND 5)');
        Schema::create('identity_consents', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('version', 32);
            $table->timestampTz('accepted_at');
            $table->char('correlation_id', 26);
            $table->unique(['user_id', 'version']);
        });
        Schema::create('identity_audit', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('operation', 32);
            $table->char('correlation_id', 26);
            $table->timestampTz('recorded_at');
        });
        DB::unprepared("CREATE FUNCTION identity_append_only() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Identity history is append-only'; END; $$;");
        foreach (['identity_consents', 'identity_audit'] as $table) {
            DB::unprepared("CREATE TRIGGER identity_history_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION identity_append_only();");
        }
    }

    public function down(): void
    {
        // Preserve identity and consent history; forward-only rollback requires a reviewed plan.
        throw new RuntimeException('Identity migration is forward-only.');
    }
};
