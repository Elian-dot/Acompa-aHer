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
        Schema::create('report_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('report_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['disk', 'path']);
            $table->index('report_id');
        });

        DB::statement('ALTER TABLE report_attachments ADD CONSTRAINT report_attachments_size_positive CHECK (size_bytes > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
