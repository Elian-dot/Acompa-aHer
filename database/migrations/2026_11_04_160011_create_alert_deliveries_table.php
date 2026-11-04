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
        Schema::create('alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('journey_participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('stage', 20);
            $table->string('status', 20)->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestampTz('last_attempt_at', 6)->nullable();
            $table->timestampTz('sent_at', 6)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index('user_id');
            $table->index('journey_participant_id');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE alert_deliveries
                ADD CONSTRAINT alert_deliveries_one_recipient CHECK (num_nonnulls(user_id, journey_participant_id) = 1),
                ADD CONSTRAINT alert_deliveries_channel_valid CHECK (channel IN ('websocket', 'push')),
                ADD CONSTRAINT alert_deliveries_stage_valid CHECK (stage IN ('preliminary', 'escalated')),
                ADD CONSTRAINT alert_deliveries_status_valid CHECK (status IN ('pending', 'sent', 'failed')),
                ADD CONSTRAINT alert_deliveries_attempts_valid CHECK (attempts >= 0 AND ((attempts = 0 AND last_attempt_at IS NULL) OR (attempts > 0 AND last_attempt_at IS NOT NULL))),
                ADD CONSTRAINT alert_deliveries_sent_valid CHECK ((status = 'sent') = (sent_at IS NOT NULL) AND (sent_at IS NULL OR attempts > 0));
            CREATE UNIQUE INDEX alert_deliveries_user_unique ON alert_deliveries (journey_alert_id, user_id, channel, stage) WHERE user_id IS NOT NULL;
            CREATE UNIQUE INDEX alert_deliveries_participant_unique ON alert_deliveries (journey_alert_id, journey_participant_id, channel, stage) WHERE journey_participant_id IS NOT NULL;
            CREATE INDEX alert_deliveries_pending_index ON alert_deliveries (journey_alert_id) WHERE status IN ('pending', 'failed');
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
    }
};
