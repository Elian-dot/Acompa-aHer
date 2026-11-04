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
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('journey_participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestampTz('expires_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index('user_id');
            $table->index('journey_participant_id');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE push_subscriptions
                ADD CONSTRAINT push_subscriptions_one_owner CHECK (num_nonnulls(user_id, journey_participant_id) = 1),
                ADD CONSTRAINT push_subscriptions_endpoint_hash_valid CHECK (endpoint_hash ~ '^[0-9a-f]{64}$'),
                ADD CONSTRAINT push_subscriptions_encoding_valid CHECK (content_encoding IN ('aes128gcm', 'aesgcm'));
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
