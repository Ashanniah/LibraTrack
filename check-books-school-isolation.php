<?php
/**
 * Check and fix books school isolation
 * Run this script to verify books table has school_id column
 * Access via: http://localhost:8000/check-books-school-isolation.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Books School Isolation Check</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Books School Isolation Check</h1>
    
    <?php
    try {
        // Check if school_id column exists
        echo "<h2>Step 1: Checking books table structure</h2>";
        $columns = DB::select("SHOW COLUMNS FROM books LIKE 'school_id'");
        
        if (count($columns) > 0) {
            echo "<p class='success'>✓ school_id column exists in books table</p>";
            
            // Check books without school_id
            echo "<h2>Step 2: Checking books without school_id</h2>";
            $booksWithoutSchool = DB::table('books')
                ->whereNull('school_id')
                ->count();
            
            if ($booksWithoutSchool > 0) {
                echo "<p class='warning'>⚠ Found {$booksWithoutSchool} book(s) without school_id</p>";
                
                // Show books without school_id
                $books = DB::table('books')
                    ->whereNull('school_id')
                    ->select('id', 'title', 'author', 'isbn')
                    ->limit(20)
                    ->get();
                
                if (count($books) > 0) {
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th></tr>";
                    foreach ($books as $book) {
                        echo "<tr>";
                        echo "<td>{$book->id}</td>";
                        echo "<td>{$book->title}</td>";
                        echo "<td>{$book->author}</td>";
                        echo "<td>{$book->isbn}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
                
                echo "<div class='code'>";
                echo "<strong>To assign these books to a school, run:</strong><br>";
                echo "<code>UPDATE books SET school_id = 1 WHERE school_id IS NULL;</code><br>";
                echo "(Replace 1 with your desired school_id)";
                echo "</div>";
            } else {
                echo "<p class='success'>✓ All books have school_id assigned</p>";
            }
            
            // Check books with school_id distribution
            echo "<h2>Step 3: Books by school</h2>";
            $booksBySchool = DB::table('books')
                ->select('school_id', DB::raw('COUNT(*) as count'))
                ->groupBy('school_id')
                ->get();
            
            echo "<table>";
            echo "<tr><th>School ID</th><th>Book Count</th></tr>";
            foreach ($booksBySchool as $row) {
                $schoolId = $row->school_id ?? 'NULL (Global)';
                echo "<tr><td>{$schoolId}</td><td>{$row->count}</td></tr>";
            }
            echo "</table>";
            
        } else {
            echo "<p class='error'>✗ school_id column does NOT exist in books table</p>";
            echo "<div class='code'>";
            echo "<strong>Run this SQL to add the column:</strong><br>";
            echo "<code>ALTER TABLE books ADD COLUMN school_id INT(11) NULL AFTER category, ADD INDEX idx_school_id (school_id);</code>";
            echo "</div>";
        }
        
        // Test filtering
        echo "<h2>Step 4: Testing school filtering</h2>";
        echo "<p>Testing book filtering for school_id = 1:</p>";
        
        $hasSchoolId = count(DB::select("SHOW COLUMNS FROM books LIKE 'school_id'")) > 0;
        if ($hasSchoolId) {
            $filteredBooks = DB::table('books')
                ->where('school_id', 1)
                ->count();
            echo "<p class='success'>✓ Found {$filteredBooks} book(s) for school_id = 1</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>
    
    <h2>Next Steps</h2>
    <ol>
        <li>If school_id column doesn't exist, run the SQL script: <code>add-school-id-to-books.sql</code></li>
        <li>Assign existing books to schools using: <code>UPDATE books SET school_id = ? WHERE school_id IS NULL;</code></li>
        <li>Test by logging in as librarians from different schools</li>
    </ol>
</body>
</html>

