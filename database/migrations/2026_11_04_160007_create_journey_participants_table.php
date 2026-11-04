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
        Schema::create('journey_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_contact_id')->constrained()->cascadeOnDelete();
            $table->char('access_token_hash', 64)->unique();
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('accepted_at', 6)->nullable();
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['journey_id', 'emergency_contact_id']);
            $table->index('emergency_contact_id');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE journey_participants
                ADD CONSTRAINT journey_participants_token_hash_valid CHECK (access_token_hash ~ '^[0-9a-f]{64}$'),
                ADD CONSTRAINT journey_participants_expiry_valid CHECK (expires_at > created_at),
                ADD CONSTRAINT journey_participants_acceptance_valid CHECK (accepted_at IS NULL OR (accepted_at >= created_at AND accepted_at < expires_at)),
                ADD CONSTRAINT journey_participants_revocation_valid CHECK (revoked_at IS NULL OR revoked_at >= created_at);
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_participants');
    }
};
