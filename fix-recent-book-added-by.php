<?php
/**
 * Fix the most recent book's added_by field
 * This script will assign the most recent book to the currently logged-in librarian
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Start session to get current user
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

echo "=== Fix Recent Book's added_by ===\n\n";

try {
    // Get current user from session
    if (empty($_SESSION['uid'])) {
        echo "❌ No user logged in. Please log in first.\n";
        echo "Current session data: " . print_r($_SESSION, true) . "\n";
        exit(1);
    }
    
    $userId = (int)$_SESSION['uid'];
    $user = DB::table('users')
        ->where('id', $userId)
        ->where('status', 'active')
        ->first();
    
    if (!$user) {
        echo "❌ User not found or inactive.\n";
        exit(1);
    }
    
    echo "Current user:\n";
    echo "  ID: " . $user->id . "\n";
    echo "  Name: " . $user->first_name . " " . $user->last_name . "\n";
    echo "  Role: " . $user->role . "\n";
    echo "  Email: " . $user->email . "\n\n";
    
    if (strtolower($user->role) !== 'librarian') {
        echo "⚠️  Warning: Current user is not a librarian (role: " . $user->role . ")\n";
        echo "The book will still be assigned to this user's ID.\n\n";
    }
    
    // Get the most recent book with NULL added_by
    $latestBook = DB::table('books')
        ->whereNull('added_by')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (!$latestBook) {
        echo "✓ No books with NULL added_by found. All books are already assigned.\n";
        exit(0);
    }
    
    echo "Most recent book with NULL added_by:\n";
    echo "  ID: " . $latestBook->id . "\n";
    echo "  Title: " . $latestBook->title . "\n";
    echo "  ISBN: " . $latestBook->isbn . "\n";
    echo "  Created: " . $latestBook->created_at . "\n\n";
    
    // Update the book
    DB::table('books')
        ->where('id', $latestBook->id)
        ->update(['added_by' => $userId]);
    
    echo "✓ Successfully assigned book ID " . $latestBook->id . " to user ID " . $userId . "\n";
    echo "  The book should now be visible to " . $user->first_name . " " . $user->last_name . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}


