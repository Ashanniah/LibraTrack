<?php
/**
 * Assign all books with NULL added_by to the currently logged-in librarian
 * Access via browser: http://localhost:8000/assign-all-books-to-librarians.php
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Start session
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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign All Books to Librarian</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f57c00; background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        button.danger { background: #f44336; }
        button.danger:hover { background: #da190b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .count { font-size: 24px; font-weight: bold; color: #4CAF50; }
    </style>
</head>
<body>
    <h1>📚 Assign All Unassigned Books to Current Librarian</h1>
    
<?php
try {
    // Get current user from session
    if (empty($_SESSION['uid'])) {
        echo '<div class="error">❌ No user logged in. Please <a href="/login.html">log in</a> first.</div>';
        exit;
    }
    
    $userId = (int)$_SESSION['uid'];
    $user = DB::table('users')
        ->where('id', $userId)
        ->where('status', 'active')
        ->first();
    
    if (!$user) {
        echo '<div class="error">❌ User not found or inactive.</div>';
        exit;
    }
    
    echo '<div class="info">';
    echo '<strong>Current User:</strong><br>';
    echo "ID: {$user->id}<br>";
    echo "Name: {$user->first_name} {$user->last_name}<br>";
    echo "Role: {$user->role}<br>";
    echo "Email: {$user->email}";
    echo '</div>';
    
    if (strtolower($user->role) !== 'librarian') {
        echo '<div class="warning">⚠️ Warning: Current user is not a librarian (role: ' . $user->role . ')</div>';
        echo '<div class="info">You can still assign books, but they will be assigned to this user account.</div>';
    }
    
    // Count unassigned books
    $unassignedCount = DB::table('books')->whereNull('added_by')->count();
    $myBooksCount = DB::table('books')->where('added_by', $userId)->count();
    $totalBooks = DB::table('books')->count();
    
    echo '<div class="info">';
    echo '<strong>Book Statistics:</strong><br>';
    echo "Total books: <span class=\"count\">{$totalBooks}</span><br>";
    echo "Unassigned books (added_by = NULL): <span class=\"count\">{$unassignedCount}</span><br>";
    echo "Books assigned to you: <span class=\"count\">{$myBooksCount}</span>";
    echo '</div>';
    
    // Handle assignment
    if (isset($_POST['assign_all']) && $_POST['assign_all'] === 'yes') {
        $updated = DB::table('books')
            ->whereNull('added_by')
            ->update(['added_by' => $userId]);
        
        echo '<div class="success">';
        echo "✓ Successfully assigned <strong>{$updated} book(s)</strong> to {$user->first_name} {$user->last_name}!<br>";
        echo "All books should now be visible in your book list.";
        echo '</div>';
        echo '<div class="info">';
        echo '<a href="/books.html" style="color: #1976d2; text-decoration: underline;">→ Go to Books Page</a>';
        echo '</div>';
        
        // Refresh counts
        $unassignedCount = 0;
        $myBooksCount = DB::table('books')->where('added_by', $userId)->count();
    }
    
    if ($unassignedCount > 0) {
        echo '<div class="warning">';
        echo "<strong>⚠️ {$unassignedCount} book(s) are unassigned.</strong><br>";
        echo "These books won't show up for any librarian because they have <code>added_by = NULL</code>.<br>";
        echo "Click the button below to assign all unassigned books to your account.";
        echo '</div>';
        
        echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to assign ALL ' . $unassignedCount . ' unassigned books to your account? This cannot be undone easily.\');">';
        echo '<input type="hidden" name="assign_all" value="yes">';
        echo '<button type="submit">Assign All ' . $unassignedCount . ' Unassigned Books to Me</button>';
        echo '</form>';
        
        // Show unassigned books
        $unassignedBooks = DB::table('books')
            ->whereNull('added_by')
            ->orderBy('created_at', 'desc')
            ->get();
        
        echo '<h2>Unassigned Books</h2>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>';
        
        foreach ($unassignedBooks as $book) {
            echo '<tr>';
            echo '<td>' . $book->id . '</td>';
            echo '<td>' . htmlspecialchars($book->title) . '</td>';
            echo '<td>' . htmlspecialchars($book->author) . '</td>';
            echo '<td>' . htmlspecialchars($book->isbn) . '</td>';
            echo '<td>' . htmlspecialchars($book->category) . '</td>';
            echo '<td>' . $book->created_at . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<div class="success">✓ All books are assigned! No unassigned books found.</div>';
    }
    
    // Show books assigned to current user
    $myBooks = DB::table('books')
        ->where('added_by', $userId)
        ->orderBy('created_at', 'desc')
        ->get();
    
    if (!$myBooks->isEmpty()) {
        echo '<h2>My Books (' . count($myBooks) . ')</h2>';
        echo '<p>These are the books assigned to your account. They should be visible in your book list.</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Created</th></tr>';
        
        foreach ($myBooks as $book) {
            echo '<tr>';
            echo '<td>' . $book->id . '</td>';
            echo '<td>' . htmlspecialchars($book->title) . '</td>';
            echo '<td>' . htmlspecialchars($book->author) . '</td>';
            echo '<td>' . htmlspecialchars($book->isbn) . '</td>';
            echo '<td>' . htmlspecialchars($book->category) . '</td>';
            echo '<td>' . $book->created_at . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '<hr>';
    echo '<div class="info">';
    echo '<strong>📝 Note:</strong> After assigning books, refresh your <a href="/books.html">Books page</a> to see them.<br>';
    echo 'New books you add will automatically be assigned to your account.';
    echo '</div>';
    
} catch (\Exception $e) {
    echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
?>
</body>
</html>


