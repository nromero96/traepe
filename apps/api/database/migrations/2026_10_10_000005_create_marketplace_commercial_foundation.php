<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DP-032: empty technical drafts; no onboarding or operational activation.
        $identifiers = static function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        };
        Schema::create('merchants', static function (Blueprint $table) use ($identifiers): void {
            $identifiers($table);
            $table->string('legal_name');
            $table->string('trade_name');
            $table->string('tax_id', 64)->nullable();
            $table->string('risk_level', 40)->nullable();
        });
        Schema::create('branches', static function (Blueprint $table) use ($identifiers): void {
            $identifiers($table);
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('market_id')->constrained('markets')->restrictOnDelete();
            $table->string('name');
            $table->string('timezone', 64);
            $table->index(['merchant_id', 'market_id']);
            $table->index('market_id');
        });
        foreach (['merchants', 'branches'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_public_id_check CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_draft_check CHECK (status = 'draft')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_version_check CHECK (version >= 1)");
        }
        foreach (['legal_name', 'trade_name'] as $column) {
            DB::statement("ALTER TABLE merchants ADD CONSTRAINT merchants_{$column}_check CHECK (length(btrim({$column})) > 0)");
        }
        DB::statement('ALTER TABLE merchants ADD CONSTRAINT merchants_tax_id_check CHECK (tax_id IS NULL), ADD CONSTRAINT merchants_risk_level_check CHECK (risk_level IS NULL)');
        foreach (['name', 'timezone'] as $column) {
            DB::statement("ALTER TABLE branches ADD CONSTRAINT branches_{$column}_check CHECK (length(btrim({$column})) > 0)");
        }
        DB::statement('ALTER TABLE branches ADD COLUMN point geography(Point,4326) NOT NULL');
        DB::statement('CREATE INDEX branches_point_gist ON branches USING GIST (point)');
        // flags=0 avoids NOTICE with spatial details. Ranges reject NaN/infinity.
        DB::statement('ALTER TABLE branches ADD CONSTRAINT branches_point_check CHECK (ST_IsValid(point::geometry, 0) AND NOT ST_IsEmpty(point::geometry) AND ST_NDims(point::geometry) = 2 AND ST_X(point::geometry) BETWEEN -180 AND 180 AND ST_Y(point::geometry) BETWEEN -90 AND 90)');
    }

    public function down(): void
    {
        throw new RuntimeException('Commercial foundation is forward-only; rollback requires a reviewed data plan.');
    }
};
