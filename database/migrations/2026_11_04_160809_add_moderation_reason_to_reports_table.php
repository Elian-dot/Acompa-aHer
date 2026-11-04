<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('moderation_reason', 50)->nullable();
        });
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_moderation_reason_check CHECK (moderation_reason IS NULL OR (status = 'discarded' AND moderation_reason IN ('insufficient_information', 'outside_coverage', 'identifying_content', 'duplicate')))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reports DROP CONSTRAINT reports_moderation_reason_check');
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('moderation_reason');
        });
    }
};
