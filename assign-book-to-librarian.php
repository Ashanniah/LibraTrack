<?php
/**
 * Assign a book to the currently logged-in librarian
 * Access this via browser: http://localhost:8000/assign-book-to-librarian.php
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
    <title>Assign Book to Librarian</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Assign Book to Current Librarian</h1>
    
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
        echo '<div class="error">⚠️ Warning: Current user is not a librarian (role: ' . $user->role . ')</div>';
    }
    
    // Handle assignment
    if (isset($_POST['assign_book_id'])) {
        $bookId = (int)$_POST['assign_book_id'];
        $book = DB::table('books')->where('id', $bookId)->first();
        
        if (!$book) {
            echo '<div class="error">❌ Book not found.</div>';
        } else {
            DB::table('books')
                ->where('id', $bookId)
                ->update(['added_by' => $userId]);
            
            echo '<div class="success">✓ Successfully assigned book "' . htmlspecialchars($book->title) . '" to you!</div>';
            echo '<div class="info">The book should now be visible in your book list. <a href="/books.html">Go to Books</a></div>';
        }
    }
    
    // Show books with NULL added_by
    $unassignedBooks = DB::table('books')
        ->whereNull('added_by')
        ->orderBy('created_at', 'desc')
        ->get();
    
    if ($unassignedBooks->isEmpty()) {
        echo '<div class="success">✓ All books are already assigned!</div>';
    } else {
        echo '<h2>Unassigned Books (added_by is NULL)</h2>';
        echo '<p>These books won\'t show up for any librarian. Click "Assign to Me" to assign them to your account.</p>';
        echo '<form method="POST">';
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Created</th><th>Action</th></tr>';
        
        foreach ($unassignedBooks as $book) {
            echo '<tr>';
            echo '<td>' . $book->id . '</td>';
            echo '<td>' . htmlspecialchars($book->title) . '</td>';
            echo '<td>' . htmlspecialchars($book->author) . '</td>';
            echo '<td>' . htmlspecialchars($book->isbn) . '</td>';
            echo '<td>' . $book->created_at . '</td>';
            echo '<td><button type="submit" name="assign_book_id" value="' . $book->id . '">Assign to Me</button></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</form>';
    }
    
    // Show books assigned to current user
    $myBooks = DB::table('books')
        ->where('added_by', $userId)
        ->orderBy('created_at', 'desc')
        ->get();
    
    if (!$myBooks->isEmpty()) {
        echo '<h2>My Books (assigned to me)</h2>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Created</th></tr>';
        
        foreach ($myBooks as $book) {
            echo '<tr>';
            echo '<td>' . $book->id . '</td>';
            echo '<td>' . htmlspecialchars($book->title) . '</td>';
            echo '<td>' . htmlspecialchars($book->author) . '</td>';
            echo '<td>' . htmlspecialchars($book->isbn) . '</td>';
            echo '<td>' . $book->created_at . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
} catch (\Exception $e) {
    echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
</body>
</html>


