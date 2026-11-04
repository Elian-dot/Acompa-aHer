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
        Schema::create('journeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->geometry('origin', subtype: 'point', srid: 4326);
            $table->geometry('destination', subtype: 'point', srid: 4326);
            $table->geometry('route', subtype: 'linestring', srid: 4326)->nullable();
            $table->timestampTz('started_at', 6);
            $table->timestampTz('ended_at', 6)->nullable();
            $table->timestampTz('last_activity_at', 6);
            $table->integer('inactivity_seconds');
            $table->integer('escalation_delay_seconds');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['user_id', 'started_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE journeys
                ADD CONSTRAINT journeys_status_valid CHECK (status IN ('active', 'completed', 'cancelled')),
                ADD CONSTRAINT journeys_lifecycle_consistent CHECK ((status = 'active' AND ended_at IS NULL) OR (status IN ('completed', 'cancelled') AND ended_at IS NOT NULL AND ended_at >= started_at)),
                ADD CONSTRAINT journeys_activity_valid CHECK (last_activity_at >= started_at AND (ended_at IS NULL OR last_activity_at <= ended_at)),
                ADD CONSTRAINT journeys_alert_intervals_positive CHECK (inactivity_seconds > 0 AND escalation_delay_seconds > 0),
                ADD CONSTRAINT journeys_origin_valid CHECK (NOT ST_IsEmpty(origin) AND ST_X(origin) BETWEEN -180 AND 180 AND ST_Y(origin) BETWEEN -90 AND 90),
                ADD CONSTRAINT journeys_destination_valid CHECK (NOT ST_IsEmpty(destination) AND ST_X(destination) BETWEEN -180 AND 180 AND ST_Y(destination) BETWEEN -90 AND 90),
                ADD CONSTRAINT journeys_route_valid CHECK (route IS NULL OR (NOT ST_IsEmpty(route) AND ST_IsValid(route) AND ST_XMin(Box3D(route)) >= -180 AND ST_XMax(Box3D(route)) <= 180 AND ST_YMin(Box3D(route)) >= -90 AND ST_YMax(Box3D(route)) <= 90));
            CREATE UNIQUE INDEX journeys_one_active_per_user ON journeys (user_id) WHERE status = 'active';
            CREATE INDEX journeys_active_last_activity_index ON journeys (last_activity_at) WHERE status = 'active';
            CREATE INDEX journeys_closed_ended_at_index ON journeys (ended_at) WHERE ended_at IS NOT NULL;
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journeys');
    }
};
