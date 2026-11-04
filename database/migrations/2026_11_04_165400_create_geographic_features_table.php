<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('geographic_features', function (Blueprint $table): void {
            $table->id();
            $table->string('osm_type', 8);
            $table->bigInteger('osm_id');
            $table->string('kind', 20);
            $table->string('name', 250)->nullable();
            $table->string('classification', 80);
            $table->string('lit', 30)->nullable();
            $table->string('foot', 30)->nullable();
            $table->string('access', 30)->nullable();
            $table->geometry('geometry', srid: 4326);
            $table->timestampTz('source_retrieved_at', 6);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['osm_type', 'osm_id']);
            $table->spatialIndex('geometry');
        });

        DB::statement("ALTER TABLE geographic_features ADD CONSTRAINT geographic_features_source_valid CHECK (osm_type IN ('node','way','relation') AND osm_id > 0), ADD CONSTRAINT geographic_features_kind_valid CHECK (kind IN ('road','shop','amenity','street_lamp')), ADD CONSTRAINT geographic_features_geometry_valid CHECK (NOT ST_IsEmpty(geometry) AND ST_IsValid(geometry) AND ST_CoveredBy(geometry, ST_MakeEnvelope(-75, 4, -74, 5, 4326)) AND ((kind = 'road' AND GeometryType(geometry) IN ('LINESTRING','MULTILINESTRING')) OR (kind <> 'road' AND GeometryType(geometry) = 'POINT')))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geographic_features');
    }
};
