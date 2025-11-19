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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor', 120)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('action', 120)->nullable();
            $table->text('details')->nullable();
            $table->unsignedInteger('school_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('school_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
