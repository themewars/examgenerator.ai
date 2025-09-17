<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'no_of_quiz') && !Schema::hasColumn('plans', 'no_of_exam')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->renameColumn('no_of_quiz', 'no_of_exam');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('plans', 'no_of_exam') && !Schema::hasColumn('plans', 'no_of_quiz')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->renameColumn('no_of_exam', 'no_of_quiz');
            });
        }
    }
};
