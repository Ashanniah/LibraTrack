<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Add school_id Column to Books Table ===\n\n";

try {
    // Check if column exists
    $columns = DB::select("SHOW COLUMNS FROM books LIKE 'school_id'");
    if (count($columns) > 0) {
        echo "✓ school_id column already exists.\n";
    } else {
        echo "Adding school_id column...\n";
        DB::statement("ALTER TABLE `books` ADD COLUMN `school_id` INT(11) NULL AFTER `category`, ADD INDEX `idx_school_id` (`school_id`)");
        echo "✓ school_id column added successfully.\n";
    }
    
    // Update existing books: set school_id based on added_by
    echo "\nUpdating existing books...\n";
    
    // Get all books with added_by set
    $books = DB::table('books')
        ->whereNotNull('added_by')
        ->get();
    
    $updated = 0;
    foreach ($books as $book) {
        // Get the librarian who added this book
        $librarian = DB::table('users')
            ->where('id', $book->added_by)
            ->where('role', 'librarian')
            ->first();
        
        if ($librarian && $librarian->school_id) {
            DB::table('books')
                ->where('id', $book->id)
                ->update(['school_id' => $librarian->school_id]);
            $updated++;
            echo "  Updated book ID {$book->id} ({$book->title}) to school_id = {$librarian->school_id}\n";
        }
    }
    
    echo "\n✓ Updated {$updated} book(s) with school_id based on their added_by librarian.\n";
    
    // Check remaining NULL books
    $nullBooks = DB::table('books')->whereNull('school_id')->count();
    if ($nullBooks > 0) {
        echo "\n⚠️  WARNING: {$nullBooks} book(s) still have school_id = NULL\n";
        echo "These books won't be visible to students. You may need to assign them manually.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}


