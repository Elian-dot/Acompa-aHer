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
        Schema::table('journeys', function (Blueprint $table): void {
            $table->geometry('activity_anchor', subtype: 'point', srid: 4326)->nullable();
            $table->timestampTz('inactivity_paused_until', 6)->nullable();
        });

        DB::statement('UPDATE journeys SET activity_anchor = origin WHERE activity_anchor IS NULL');
        DB::statement('ALTER TABLE journeys ADD CONSTRAINT journeys_activity_anchor_valid CHECK (activity_anchor IS NULL OR (NOT ST_IsEmpty(activity_anchor) AND ST_X(activity_anchor) BETWEEN -180 AND 180 AND ST_Y(activity_anchor) BETWEEN -90 AND 90))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE journeys DROP CONSTRAINT journeys_activity_anchor_valid');

        Schema::table('journeys', function (Blueprint $table): void {
            $table->dropColumn(['activity_anchor', 'inactivity_paused_until']);
        });
    }
};
