<?php
/**
 * Assign books without school_id to the librarian's school who is currently logged in
 * This script assigns all NULL school_id books to the logged-in librarian's school
 * Access via: http://localhost:8000/assign-books-to-librarian-school.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/html; charset=utf-8');

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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Books to Librarian's School</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 Assign Books to Your School</h1>
        
        <?php
        try {
            // Check if user is logged in
            if (empty($_SESSION['uid'])) {
                echo "<div class='error'>✗ You must be logged in to use this tool.</div>";
                echo "<p><a href='/login.html'>Go to Login</a></p>";
                exit;
            }

            $uid = (int)$_SESSION['uid'];
            $user = DB::table('users')
                ->leftJoin('schools', 'users.school_id', '=', 'schools.id')
                ->where('users.id', $uid)
                ->select('users.id', 'users.role', 'users.school_id', 'schools.name as school_name')
                ->first();

            if (!$user) {
                echo "<div class='error'>✗ User not found.</div>";
                exit;
            }

            $userRole = strtolower($user->role ?? '');
            $userSchoolId = (int)($user->school_id ?? 0);

            if ($userRole !== 'librarian' && $userRole !== 'admin') {
                echo "<div class='error'>✗ Only librarians and admins can use this tool.</div>";
                exit;
            }

            // Check if school_id column exists
            $columns = DB::select("SHOW COLUMNS FROM books LIKE 'school_id'");
            $hasSchoolId = count($columns) > 0;

            if (!$hasSchoolId) {
                echo "<div class='warning'>⚠ school_id column does NOT exist in books table</div>";
                echo "<div class='info'>";
                echo "<p><strong>Please run this SQL first:</strong></p>";
                echo "<code>ALTER TABLE books ADD COLUMN school_id INT(11) NULL AFTER category, ADD INDEX idx_school_id (school_id);</code>";
                echo "<p>Or use the fix script: <a href='fix-books-isolation.php'>fix-books-isolation.php</a></p>";
                echo "</div>";
                exit;
            }

            echo "<div class='info'>";
            echo "<p><strong>Logged in as:</strong> " . htmlspecialchars($user->role) . "</p>";
            if ($userSchoolId > 0) {
                echo "<p><strong>Your School:</strong> " . htmlspecialchars($user->school_name ?? 'School ID ' . $userSchoolId) . " (ID: {$userSchoolId})</p>";
            } else {
                echo "<p><strong>Your School:</strong> Not assigned</p>";
            }
            echo "</div>";

            // Count books without school_id
            $booksWithoutSchool = DB::table('books')->whereNull('school_id')->count();

            if ($booksWithoutSchool > 0) {
                echo "<h2>Found {$booksWithoutSchool} book(s) without school_id</h2>";
                
                if ($userSchoolId > 0) {
                    echo "<div class='warning'>";
                    echo "<p>These books will be assigned to your school: <strong>" . htmlspecialchars($user->school_name ?? 'School ID ' . $userSchoolId) . "</strong></p>";
                    echo "</div>";

                    if (isset($_POST['assign']) && $_POST['assign'] === 'yes') {
                        $affected = DB::table('books')
                            ->whereNull('school_id')
                            ->update(['school_id' => $userSchoolId]);
                        
                        echo "<div class='success'>";
                        echo "<h3>✅ Success!</h3>";
                        echo "<p>Assigned {$affected} book(s) to your school (ID: {$userSchoolId})</p>";
                        echo "</div>";
                    } else {
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='assign' value='yes'>";
                        echo "<button type='submit' class='danger'>Assign All {$booksWithoutSchool} Books to My School</button>";
                        echo "</form>";
                    }
                } else {
                    echo "<div class='error'>";
                    echo "<p>⚠ You don't have a school assigned. Please contact an admin to assign you to a school.</p>";
                    echo "</div>";
                }
            } else {
                echo "<div class='success'>";
                echo "<h3>✅ All books have school_id assigned!</h3>";
                echo "</div>";
            }

            // Show current book distribution
            echo "<h2>Current Book Distribution</h2>";
            $booksBySchool = DB::table('books')
                ->select('school_id', DB::raw('COUNT(*) as count'))
                ->groupBy('school_id')
                ->get();

            echo "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
            echo "<tr style='background: #f8f9fa;'><th style='padding: 10px; border: 1px solid #ddd;'>School ID</th><th style='padding: 10px; border: 1px solid #ddd;'>School Name</th><th style='padding: 10px; border: 1px solid #ddd;'>Book Count</th></tr>";
            foreach ($booksBySchool as $row) {
                $schoolId = $row->school_id ?? 'NULL (Unassigned)';
                $schoolName = '';
                if ($row->school_id) {
                    $school = DB::table('schools')->where('id', $row->school_id)->first();
                    $schoolName = $school ? $school->name : 'Unknown';
                } else {
                    $schoolName = 'Unassigned Books';
                }
                $highlight = ($row->school_id == $userSchoolId) ? " style='background: #d4edda;'" : '';
                echo "<tr{$highlight}>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$schoolId}</td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($schoolName) . "</td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$row->count}</td>";
                echo "</tr>";
            }
            echo "</table>";

        } catch (Exception $e) {
            echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
        
        <h2>Next Steps</h2>
        <ol>
            <li>After assigning books, refresh your books page</li>
            <li>Verify that your books are now visible</li>
            <li>New books you add will automatically be assigned to your school</li>
        </ol>
    </div>
</body>
</html>


