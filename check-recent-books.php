<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Recent Books Check ===\n\n";

try {
    // Check if added_by column exists
    $hasAddedBy = false;
    $columns = DB::select('SHOW COLUMNS FROM books LIKE \'added_by\'');
    if (count($columns) > 0) {
        $hasAddedBy = true;
        echo "✓ added_by column EXISTS\n\n";
    } else {
        echo "✗ added_by column MISSING\n\n";
    }
    
    // Get the 5 most recent books
    $books = DB::table('books')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent books (last 5):\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($books->isEmpty()) {
        echo "No books found in database.\n";
    } else {
        foreach ($books as $book) {
            $addedBy = $hasAddedBy ? ($book->added_by ?? 'NULL') : 'N/A (column missing)';
            echo sprintf("ID: %-5d | Title: %-30s | added_by: %s\n", 
                $book->id, 
                substr($book->title, 0, 30),
                $addedBy
            );
        }
    }
    
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "Total books in database: " . DB::table('books')->count() . "\n";
    
    if ($hasAddedBy) {
        $booksWithAddedBy = DB::table('books')->whereNotNull('added_by')->count();
        $booksWithoutAddedBy = DB::table('books')->whereNull('added_by')->count();
        echo "Books with added_by set: $booksWithAddedBy\n";
        echo "Books with added_by NULL: $booksWithoutAddedBy\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


