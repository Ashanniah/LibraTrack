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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('book_id');
            $table->timestamp('borrowed_at')->useCurrent();
            $table->date('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->date('extended_due_at')->nullable();
            $table->enum('status', ['borrowed', 'returned'])->default('borrowed');
            $table->unsignedInteger('school_id')->nullable();
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
            $table->foreign('request_id')->references('id')->on('borrow_requests')->onDelete('set null');
            $table->index('student_id');
            $table->index('book_id');
            $table->index('status');
            $table->index('due_at');
            $table->index('borrowed_at');
            $table->index('request_id');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
