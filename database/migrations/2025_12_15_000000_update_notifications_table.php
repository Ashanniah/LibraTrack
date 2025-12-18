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
        // Drop old notifications table if it exists with old structure
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        
        // Create new notifications table with required structure
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_user_id');
            $table->enum('recipient_role', ['admin', 'librarian', 'student']);
            $table->string('type', 50);
            $table->string('title', 120);
            $table->text('message');
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('deep_link', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            
            // Indexes
            $table->index(['recipient_user_id', 'is_read', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            
            // Foreign key
            $table->foreign('recipient_user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
        // Create notification_deliveries table (email delivery tracking)
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_user_id');
            $table->string('type', 50);
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('channel', ['email'])->default('email');
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->text('error')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            
            // Indexes
            $table->index(['recipient_user_id', 'type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['type', 'entity_type', 'entity_id']);
            
            // Foreign key
            $table->foreign('recipient_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};

