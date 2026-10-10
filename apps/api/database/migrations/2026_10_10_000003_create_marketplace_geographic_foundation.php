<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DP-028: Marketplace owns these empty tables; no operational activation.
        $identifiers = static function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        };
        Schema::create('countries', static function (Blueprint $table) use ($identifiers): void {
            $identifiers($table);
            $table->char('code', 2)->unique();
            $table->string('name');
            $table->char('currency_code', 3);
        });
        Schema::create('markets', static function (Blueprint $table) use ($identifiers): void {
            $identifiers($table);
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('name');
            $table->string('timezone', 64);
            $table->char('currency_code', 3);
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->index('country_id');
        });
        Schema::create('service_zones', static function (Blueprint $table) use ($identifiers): void {
            $identifiers($table);
            $table->foreignId('market_id')->constrained('markets')->restrictOnDelete();
            $table->string('name');
            $table->string('zone_type', 40)->default('fixture');
            $table->integer('priority');
            $table->string('status', 40)->default('draft');
            $table->integer('version')->default(1);
            $table->index('market_id');
        });
        DB::statement('ALTER TABLE service_zones ADD COLUMN polygon geography(MultiPolygon,4326) NOT NULL');
        DB::statement('CREATE INDEX service_zones_polygon_gist ON service_zones USING GIST (polygon)');
        foreach (['countries', 'markets', 'service_zones'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_public_id_check CHECK (public_id ~ '^[0-7][0-9A-HJKMNP-TV-Z]{25}$')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_name_check CHECK (length(btrim(name)) > 0)");
        }
        foreach (['countries', 'markets'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_currency_code_check CHECK (currency_code ~ '^[A-Z]{3}$')");
        }
        DB::statement("ALTER TABLE countries ADD CONSTRAINT countries_code_check CHECK (code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE markets ADD CONSTRAINT markets_timezone_check CHECK (length(btrim(timezone)) > 0)');
        foreach (['markets', 'service_zones'] as $name) {
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_draft_check CHECK (status = 'draft')");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_version_check CHECK (version >= 1)");
        }
        DB::statement("ALTER TABLE service_zones ADD CONSTRAINT service_zones_type_check CHECK (zone_type = 'fixture')");
        // flags=0 avoids NOTICE containing spatial details for invalid input.
        DB::statement('ALTER TABLE service_zones ADD CONSTRAINT service_zones_polygon_check CHECK (ST_IsValid(polygon::geometry, 0) AND NOT ST_IsEmpty(polygon::geometry) AND ST_NDims(polygon::geometry) = 2)');
    }

    public function down(): void
    {
        throw new RuntimeException('Geographic foundation is forward-only; rollback requires a reviewed data plan.');
    }
};
