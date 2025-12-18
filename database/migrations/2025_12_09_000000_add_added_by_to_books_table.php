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
        Schema::table('books', function (Blueprint $table) {
            // Check if added_by column already exists before adding
            if (!Schema::hasColumn('books', 'added_by')) {
                $table->unsignedInteger('added_by')->nullable()->after('school_id');
                $table->index('added_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'added_by')) {
                $table->dropIndex(['added_by']);
                $table->dropColumn('added_by');
            }
        });
    }
};


