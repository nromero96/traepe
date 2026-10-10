<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['identity_roles', 'identity_permissions'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->string('code', 128)->unique();
            });
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_reference_check CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_code_check CHECK (code ~ '^[a-z][a-z0-9_.-]{0,127}$')");
        }
        Schema::create('identity_role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('identity_roles');
            $table->foreignId('permission_id')->constrained('identity_permissions');
            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });
        Schema::create('identity_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('role_id')->constrained('identity_roles');
            $table->string('scope_type', 16);
            $table->char('scope_public_id', 26);
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_public_id'], 'identity_role_scope_unique');
            $table->index('role_id');
        });
        Schema::create('identity_permission_grants', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('permission_id')->constrained('identity_permissions');
            $table->string('scope_type', 16);
            $table->char('scope_public_id', 26);
            $table->string('effect', 8);
            $table->timestampTz('expires_at')->nullable();
            $table->index(['user_id', 'permission_id', 'scope_type', 'scope_public_id'], 'identity_grant_lookup');
            $table->index('permission_id');
            $table->index('expires_at');
        });
        foreach (['identity_role_assignments', 'identity_permission_grants'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_reference_check CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$' AND scope_public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_scope_check CHECK (scope_type IN ('platform', 'merchant', 'branch'))");
        }
        DB::statement("ALTER TABLE identity_permission_grants ADD CONSTRAINT identity_grant_effect_check CHECK (effect IN ('allow', 'deny'))");
    }

    public function down(): void
    {
        throw new RuntimeException('Authorization directory is forward-only; rollback requires a reviewed data plan.');
    }
};
