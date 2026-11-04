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
        Schema::create('journey_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->geometry('position', subtype: 'point', srid: 4326);
            $table->decimal('accuracy_meters', 8, 2);
            $table->timestampTz('recorded_at', 6);
            $table->timestampTz('received_at', 6)->useCurrent();
            $table->unique(['journey_id', 'recorded_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE journey_locations
                ADD CONSTRAINT journey_locations_accuracy_valid CHECK (accuracy_meters >= 0),
                ADD CONSTRAINT journey_locations_position_valid CHECK (NOT ST_IsEmpty(position) AND ST_X(position) BETWEEN -180 AND 180 AND ST_Y(position) BETWEEN -90 AND 90);
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_locations');
    }
};
