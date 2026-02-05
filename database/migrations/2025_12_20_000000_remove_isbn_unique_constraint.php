<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove unique constraint from ISBN to allow same ISBN for different users
     * Keep index for performance but allow duplicates
     */
    public function up(): void
    {
        try {
            // Check if unique constraint exists and remove it
            // MySQL specific - check for unique index
            $indexes = DB::select("SHOW INDEX FROM books WHERE Key_name = 'books_isbn_unique'");
            
            if (count($indexes) > 0) {
                // Drop the unique index
                DB::statement('ALTER TABLE books DROP INDEX books_isbn_unique');
            }
            
            // Also check for any other unique constraints on ISBN
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'books' 
                AND CONSTRAINT_TYPE = 'UNIQUE'
                AND CONSTRAINT_NAME LIKE '%isbn%'
            ");
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE books DROP INDEX {$constraint->CONSTRAINT_NAME}");
            }
            
            // Keep a regular index on ISBN for performance (non-unique)
            // Check if regular index exists
            $regularIndex = DB::select("SHOW INDEX FROM books WHERE Key_name = 'books_isbn_index'");
            if (count($regularIndex) === 0) {
                DB::statement('ALTER TABLE books ADD INDEX books_isbn_index (isbn)');
            }
        } catch (\Exception $e) {
            // If migration fails, log but don't stop
            error_log("ISBN unique constraint removal migration error: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     * Re-add unique constraint (not recommended, but included for completeness)
     */
    public function down(): void
    {
        try {
            // Remove regular index if it exists
            $regularIndex = DB::select("SHOW INDEX FROM books WHERE Key_name = 'books_isbn_index'");
            if (count($regularIndex) > 0) {
                DB::statement('ALTER TABLE books DROP INDEX books_isbn_index');
            }
            
            // Re-add unique constraint (WARNING: This will fail if duplicates exist)
            // Only do this if you're sure there are no duplicate ISBNs
            DB::statement('ALTER TABLE books ADD UNIQUE INDEX books_isbn_unique (isbn)');
        } catch (\Exception $e) {
            error_log("ISBN unique constraint restoration migration error: " . $e->getMessage());
        }
    }
};


