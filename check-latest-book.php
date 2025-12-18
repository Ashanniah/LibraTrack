<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Latest Book Check ===\n\n";

try {
    // Get the most recent book
    $latestBook = DB::table('books')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($latestBook) {
        echo "Latest book:\n";
        echo "ID: " . $latestBook->id . "\n";
        echo "Title: " . $latestBook->title . "\n";
        echo "ISBN: " . $latestBook->isbn . "\n";
        echo "added_by: " . ($latestBook->added_by ?? 'NULL') . "\n";
        echo "created_at: " . $latestBook->created_at . "\n";
        echo "\n";
        
        // Check if added_by column exists and has index
        $columns = DB::select('SHOW COLUMNS FROM books WHERE Field = \'added_by\'');
        if (count($columns) > 0) {
            echo "✓ added_by column exists\n";
            $col = $columns[0];
            echo "  Type: " . $col->Type . "\n";
            echo "  Null: " . $col->Null . "\n";
        } else {
            echo "✗ added_by column missing\n";
        }
        
        // Check indexes
        $indexes = DB::select("SHOW INDEXES FROM books WHERE Column_name = 'added_by'");
        if (count($indexes) > 0) {
            echo "✓ Index on added_by exists\n";
        } else {
            echo "✗ No index on added_by\n";
        }
    } else {
        echo "No books found in database.\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


