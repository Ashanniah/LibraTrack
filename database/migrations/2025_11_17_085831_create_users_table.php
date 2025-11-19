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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('email', 150)->unique();
            $table->string('student_no', 8)->nullable();
            $table->string('course', 255)->nullable();
            $table->string('year_level', 10)->nullable();
            $table->string('section', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('password');
            $table->enum('role', ['student', 'librarian', 'admin'])->default('student');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->unsignedInteger('school_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            
            $table->index('school_id');
            $table->index('student_no');
            $table->index('role');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
