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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('isbn')->unique();
            $table->string('author');
            $table->string('category');
            $table->integer('quantity')->default(0);
            $table->string('publisher')->nullable();
            $table->date('date_published')->nullable();
            $table->string('cover')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            
            $table->index('category');
            $table->index('isbn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
