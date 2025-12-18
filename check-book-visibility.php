<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Book Visibility Check ===\n\n";

try {
    // Find "The Science Book"
    $book = DB::table('books')
        ->where('title', 'LIKE', '%Science Book%')
        ->first();
    
    if (!$book) {
        echo "❌ 'The Science Book' not found in database.\n";
        exit(1);
    }
    
    echo "Book Found:\n";
    echo "  ID: {$book->id}\n";
    echo "  Title: {$book->title}\n";
    echo "  ISBN: {$book->isbn}\n";
    echo "  Author: {$book->author}\n";
    echo "  added_by: " . ($book->added_by ?? 'NULL') . "\n";
    echo "  created_at: {$book->created_at}\n\n";
    
    // Find Edmar and Jane
    $edmar = DB::table('users')
        ->where('first_name', 'LIKE', '%Edmar%')
        ->orWhere('email', 'LIKE', '%edmar%')
        ->where('role', 'librarian')
        ->first();
    
    $jane = DB::table('users')
        ->where('first_name', 'LIKE', '%Jane%')
        ->orWhere('email', 'LIKE', '%jane%')
        ->where('role', 'librarian')
        ->first();
    
    echo "Librarians:\n";
    if ($edmar) {
        echo "  Edmar - ID: {$edmar->id}, Email: {$edmar->email}, School ID: " . ($edmar->school_id ?? 'NULL') . "\n";
    } else {
        echo "  Edmar - NOT FOUND\n";
    }
    
    if ($jane) {
        echo "  Jane - ID: {$jane->id}, Email: {$jane->email}, School ID: " . ($jane->school_id ?? 'NULL') . "\n";
    } else {
        echo "  Jane - NOT FOUND\n";
    }
    
    echo "\n";
    
    // Check what books each librarian should see
    if ($edmar) {
        $edmarBooks = DB::table('books')
            ->where('added_by', $edmar->id)
            ->count();
        echo "Books Edmar should see (added_by = {$edmar->id}): {$edmarBooks}\n";
        
        $edmarBookList = DB::table('books')
            ->where('added_by', $edmar->id)
            ->select('id', 'title', 'added_by')
            ->get();
        foreach ($edmarBookList as $b) {
            echo "  - ID {$b->id}: {$b->title} (added_by: {$b->added_by})\n";
        }
    }
    
    echo "\n";
    
    if ($jane) {
        $janeBooks = DB::table('books')
            ->where('added_by', $jane->id)
            ->count();
        echo "Books Jane should see (added_by = {$jane->id}): {$janeBooks}\n";
        
        $janeBookList = DB::table('books')
            ->where('added_by', $jane->id)
            ->select('id', 'title', 'added_by')
            ->get();
        foreach ($janeBookList as $b) {
            echo "  - ID {$b->id}: {$b->title} (added_by: {$b->added_by})\n";
        }
        
        // Check if "The Science Book" is in Jane's list
        $janeCanSee = DB::table('books')
            ->where('id', $book->id)
            ->where('added_by', $jane->id)
            ->exists();
        
        echo "\n";
        if ($janeCanSee) {
            echo "⚠️  PROBLEM: Jane CAN see 'The Science Book' (added_by matches Jane's ID)\n";
        } else {
            echo "✓ Jane CANNOT see 'The Science Book' (added_by doesn't match Jane's ID)\n";
        }
    }
    
    // Check all books with NULL added_by
    $nullBooks = DB::table('books')->whereNull('added_by')->count();
    echo "\nBooks with added_by = NULL: {$nullBooks}\n";
    if ($nullBooks > 0) {
        echo "⚠️  WARNING: Books with NULL added_by will be visible to ALL librarians if filtering isn't working!\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}


