<?php
/**
 * Auto-fix books school isolation
 * Run this script to automatically add school_id column and assign existing books
 * Access via: http://localhost:8000/fix-books-isolation.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/html; charset=utf-8');

$messages = [];
$errors = [];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Books School Isolation</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; border-left: 4px solid #007bff; }
        h1 { color: #333; }
        h2 { color: #555; margin-top: 30px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .danger { background: #dc3545; }
        .danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Fix Books School Isolation</h1>
        
        <?php
        try {
            // Step 1: Check if column exists
            echo "<h2>Step 1: Checking books table structure</h2>";
            $columns = DB::select("SHOW COLUMNS FROM books LIKE 'school_id'");
            
            if (count($columns) > 0) {
                echo "<div class='success'>✓ school_id column already exists in books table</div>";
                $columnExists = true;
            } else {
                echo "<div class='warning'>⚠ school_id column does NOT exist - will add it now</div>";
                $columnExists = false;
            }
            
            // Step 2: Add column if it doesn't exist
            if (!$columnExists) {
                echo "<h2>Step 2: Adding school_id column</h2>";
                try {
                    DB::statement('ALTER TABLE `books` ADD COLUMN `school_id` INT(11) NULL AFTER `category`');
                    DB::statement('ALTER TABLE `books` ADD INDEX `idx_school_id` (`school_id`)');
                    echo "<div class='success'>✓ school_id column added successfully!</div>";
                    $columnExists = true;
                } catch (Exception $e) {
                    echo "<div class='error'>✗ Error adding column: " . htmlspecialchars($e->getMessage()) . "</div>";
                    $errors[] = $e->getMessage();
                }
            } else {
                echo "<h2>Step 2: Column already exists - skipping</h2>";
            }
            
            if ($columnExists) {
                // Step 3: Check books without school_id
                echo "<h2>Step 3: Checking books without school_id</h2>";
                $booksWithoutSchool = DB::table('books')
                    ->whereNull('school_id')
                    ->count();
                
                if ($booksWithoutSchool > 0) {
                    echo "<div class='warning'>⚠ Found {$booksWithoutSchool} book(s) without school_id assigned</div>";
                    
                    // Get list of schools
                    $schools = DB::table('schools')->select('id', 'name')->get();
                    
                    if (count($schools) > 0) {
                        echo "<h2>Step 4: Assign books to schools</h2>";
                        echo "<div class='info'>";
                        echo "<p><strong>Found " . count($schools) . " school(s) in database:</strong></p>";
                        echo "<ul>";
                        foreach ($schools as $school) {
                            echo "<li>School ID {$school->id}: {$school->name}</li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                        
                        echo "<div class='code'>";
                        echo "<p><strong>You have {$booksWithoutSchool} book(s) without school_id.</strong></p>";
                        echo "<p>To assign them to a school, click the button below:</p>";
                        echo "</div>";
                        
                        echo "<form method='POST' style='margin: 20px 0;'>";
                        echo "<label><strong>Assign all books without school_id to:</strong></label><br><br>";
                        echo "<select name='school_id' style='padding: 8px; width: 300px;'>";
                        echo "<option value=''>-- Select School --</option>";
                        foreach ($schools as $school) {
                            echo "<option value='{$school->id}'>{$school->name} (ID: {$school->id})</option>";
                        }
                        echo "</select><br><br>";
                        echo "<button type='submit' name='action' value='assign' class='danger'>Assign Books to Selected School</button>";
                        echo "</form>";
                        
                        // Handle assignment
                        if (isset($_POST['action']) && $_POST['action'] === 'assign' && !empty($_POST['school_id'])) {
                            $selectedSchoolId = (int)$_POST['school_id'];
                            if ($selectedSchoolId > 0) {
                                $affected = DB::table('books')
                                    ->whereNull('school_id')
                                    ->update(['school_id' => $selectedSchoolId]);
                                echo "<div class='success'>✓ Successfully assigned {$affected} book(s) to School ID {$selectedSchoolId}</div>";
                                $booksWithoutSchool = 0; // Update count
                            }
                        }
                        
                    } else {
                        echo "<div class='warning'>⚠ No schools found in database. Please create schools first.</div>";
                    }
                } else {
                    echo "<div class='success'>✓ All books have school_id assigned!</div>";
                }
                
                // Step 5: Show summary
                echo "<h2>Step 5: Summary</h2>";
                $booksBySchool = DB::table('books')
                    ->select('school_id', DB::raw('COUNT(*) as count'))
                    ->groupBy('school_id')
                    ->get();
                
                echo "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
                echo "<tr style='background: #f8f9fa;'><th style='padding: 10px; border: 1px solid #ddd;'>School ID</th><th style='padding: 10px; border: 1px solid #ddd;'>Book Count</th></tr>";
                foreach ($booksBySchool as $row) {
                    $schoolId = $row->school_id ?? 'NULL (Unassigned)';
                    $schoolName = '';
                    if ($row->school_id) {
                        $school = DB::table('schools')->where('id', $row->school_id)->first();
                        $schoolName = $school ? " - {$school->name}" : '';
                    }
                    echo "<tr>";
                    echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$schoolId}{$schoolName}</td>";
                    echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$row->count}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                if ($booksWithoutSchool == 0) {
                    echo "<div class='success' style='margin-top: 20px;'>";
                    echo "<h3>✅ All Done!</h3>";
                    echo "<p>Books are now properly isolated by school. Librarians and students will only see books from their own school.</p>";
                    echo "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
        
        <h2>Next Steps</h2>
        <ol>
            <li>Test by logging in as librarians from different schools</li>
            <li>Verify that books from one school don't appear for other schools</li>
            <li>Check the diagnostic page: <a href="check-books-school-isolation.php">check-books-school-isolation.php</a></li>
        </ol>
    </div>
</body>
</html>

