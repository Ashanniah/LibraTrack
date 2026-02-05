<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create email_logs table to store all email notifications sent
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Recipient user ID
            $table->string('role', 20)->nullable(); // Recipient role (student, librarian, admin)
            $table->string('recipient_email', 255); // Email address
            $table->string('event_type', 100); // BORROW_APPROVED, LOW_STOCK, etc.
            $table->string('subject', 500); // Email subject
            $table->text('body_preview')->nullable(); // First 500 chars of email body
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error_message')->nullable(); // Error if failed
            $table->string('related_entity_type', 50)->nullable(); // loan, book, borrow_request, etc.
            $table->unsignedBigInteger('related_entity_id')->nullable(); // Related entity ID
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('sent_at')->nullable(); // When email was actually sent
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('role');
            $table->index('event_type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};


