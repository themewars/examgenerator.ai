<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'pdf_to_exam_enabled')) {
                $table->boolean('pdf_to_exam_enabled')->default(false)->after('ppt_quiz_enabled');
            }
            if (!Schema::hasColumn('plans', 'website_quiz_enabled')) {
                $table->boolean('website_quiz_enabled')->default(false)->after('pdf_to_exam_enabled');
            }
        });

        // Backfill: if youtube_quiz_enabled exists, copy values to website_quiz_enabled
        if (Schema::hasColumn('plans', 'youtube_quiz_enabled') && Schema::hasColumn('plans', 'website_quiz_enabled')) {
            DB::statement('UPDATE plans SET website_quiz_enabled = youtube_quiz_enabled');
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'website_quiz_enabled')) {
                $table->dropColumn('website_quiz_enabled');
            }
            if (Schema::hasColumn('plans', 'pdf_to_exam_enabled')) {
                $table->dropColumn('pdf_to_exam_enabled');
            }
        });
    }
};


