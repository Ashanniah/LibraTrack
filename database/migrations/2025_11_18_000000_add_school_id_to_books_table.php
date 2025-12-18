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
            // Check if school_id column already exists before adding
            if (!Schema::hasColumn('books', 'school_id')) {
                $table->unsignedInteger('school_id')->nullable()->after('category');
                $table->index('school_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'school_id')) {
                $table->dropIndex(['school_id']);
                $table->dropColumn('school_id');
            }
        });
    }
};

