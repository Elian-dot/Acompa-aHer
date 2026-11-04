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
        Schema::create('journey_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('kind', 20);
            $table->string('status', 20)->default('preliminary');
            $table->timestampTz('inactivity_started_at', 6)->nullable();
            $table->timestampTz('triggered_at', 6);
            $table->timestampTz('escalation_due_at', 6)->nullable();
            $table->timestampTz('confirmed_at', 6)->nullable();
            $table->timestampTz('acknowledged_at', 6)->nullable();
            $table->timestampTz('resolved_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE journey_alerts
                ADD CONSTRAINT journey_alerts_kind_valid CHECK (kind IN ('inactivity', 'emergency')),
                ADD CONSTRAINT journey_alerts_status_valid CHECK (status IN ('preliminary', 'escalated', 'acknowledged', 'resolved')),
                ADD CONSTRAINT journey_alerts_kind_fields_valid CHECK (
                    (kind = 'inactivity' AND inactivity_started_at IS NOT NULL AND inactivity_started_at <= triggered_at AND escalation_due_at IS NOT NULL AND escalation_due_at > triggered_at AND confirmed_at IS NULL)
                    OR (kind = 'emergency' AND inactivity_started_at IS NULL AND escalation_due_at IS NULL AND confirmed_at IS NOT NULL AND confirmed_at <= triggered_at AND status <> 'preliminary')
                ),
                ADD CONSTRAINT journey_alerts_acknowledgment_valid CHECK (status <> 'acknowledged' OR acknowledged_at IS NOT NULL),
                ADD CONSTRAINT journey_alerts_resolution_valid CHECK ((status = 'resolved') = (resolved_at IS NOT NULL)),
                ADD CONSTRAINT journey_alerts_timestamps_valid CHECK ((acknowledged_at IS NULL OR acknowledged_at >= triggered_at) AND (resolved_at IS NULL OR resolved_at >= triggered_at));
            CREATE UNIQUE INDEX journey_alerts_inactivity_cycle_unique ON journey_alerts (journey_id, inactivity_started_at) WHERE kind = 'inactivity';
            CREATE INDEX journey_alerts_journey_triggered_index ON journey_alerts (journey_id, triggered_at);
            CREATE INDEX journey_alerts_pending_escalation_index ON journey_alerts (escalation_due_at) WHERE status = 'preliminary';
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_alerts');
    }
};
