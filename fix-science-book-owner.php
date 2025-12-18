<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Fix 'The Science Book' Owner ===\n\n";

// Find the book
$book = DB::table('books')
    ->where('title', 'LIKE', '%Science Book%')
    ->first();

if (!$book) {
    echo "❌ Book not found.\n";
    exit(1);
}

echo "Current book data:\n";
echo "  ID: {$book->id}\n";
echo "  Title: {$book->title}\n";
echo "  Current added_by: " . ($book->added_by ?? 'NULL') . "\n\n";

// Find Edmar
$edmar = DB::table('users')
    ->where('first_name', 'LIKE', '%Edmar%')
    ->orWhere('email', 'LIKE', '%edmar%')
    ->where('role', 'librarian')
    ->first();

if (!$edmar) {
    echo "❌ Edmar not found.\n";
    exit(1);
}

echo "Edmar's data:\n";
echo "  ID: {$edmar->id}\n";
echo "  Name: {$edmar->first_name} {$edmar->last_name}\n";
echo "  Email: {$edmar->email}\n\n";

// Update the book
DB::table('books')
    ->where('id', $book->id)
    ->update(['added_by' => $edmar->id]);

echo "✓ Successfully updated 'The Science Book' to be owned by Edmar (ID: {$edmar->id})\n";
echo "\nNow:\n";
echo "  - Edmar should see this book\n";
echo "  - Jane should NOT see this book\n";


