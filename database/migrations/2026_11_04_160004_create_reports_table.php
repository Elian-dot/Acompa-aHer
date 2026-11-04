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
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('report_category_id');
            $table->foreign('report_category_id')->references('id')->on('report_categories')->restrictOnDelete();
            $table->boolean('is_anonymous')->default(true);
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->timestampTz('occurred_at', 6);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('moderated_at', 6)->nullable();
            $table->timestampTz('anonymized_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->spatialIndex('location');
            $table->index(['report_category_id', 'occurred_at']);
            $table->index('user_id');
            $table->index('moderated_by');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE reports
                ADD CONSTRAINT reports_uuid_v4 CHECK (id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
                ADD CONSTRAINT reports_anonymous_author CHECK (NOT is_anonymous OR user_id IS NULL),
                ADD CONSTRAINT reports_status_valid CHECK (status IN ('pending', 'verified', 'discarded')),
                ADD CONSTRAINT reports_moderation_consistent CHECK ((status = 'pending' AND moderated_at IS NULL AND moderated_by IS NULL) OR (status IN ('verified', 'discarded') AND moderated_at IS NOT NULL)),
                ADD CONSTRAINT reports_anonymization_consistent CHECK (anonymized_at IS NULL OR (is_anonymous AND user_id IS NULL)),
                ADD CONSTRAINT reports_location_valid CHECK (NOT ST_IsEmpty(location) AND ST_X(location) BETWEEN -180 AND 180 AND ST_Y(location) BETWEEN -90 AND 90);
            CREATE INDEX reports_verified_occurred_at_index ON reports (occurred_at) WHERE status = 'verified';
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
