<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->unique();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        } else {
            // Ensure required columns exist
            Schema::table('schools', function (Blueprint $table) {
                if (!Schema::hasColumn('schools', 'status')) {
                    $table->enum('status', ['active', 'inactive'])->default('active')->after('name');
                }
                if (!Schema::hasColumn('schools', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

