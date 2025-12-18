<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Books Table Structure ===\n\n";

try {
    $columns = DB::select('SHOW COLUMNS FROM books');
    
    echo "Total columns: " . count($columns) . "\n\n";
    
    $hasAddedBy = false;
    $hasSchoolId = false;
    
    foreach ($columns as $col) {
        $null = $col->Null === 'YES' ? 'YES' : 'NO';
        $default = $col->Default !== null ? $col->Default : 'NULL';
        echo sprintf("%-20s | %-20s | Null: %-3s | Default: %s\n", 
            $col->Field, 
            $col->Type, 
            $null,
            $default
        );
        
        if ($col->Field === 'added_by') {
            $hasAddedBy = true;
        }
        if ($col->Field === 'school_id') {
            $hasSchoolId = true;
        }
    }
    
    echo "\n=== Status ===\n";
    echo "added_by column: " . ($hasAddedBy ? "✓ EXISTS" : "✗ MISSING") . "\n";
    echo "school_id column: " . ($hasSchoolId ? "✓ EXISTS" : "✗ MISSING") . "\n";
    
    if (!$hasAddedBy) {
        echo "\n⚠️  The 'added_by' column is missing. You need to add it for librarian isolation to work.\n";
        echo "Run the migration or SQL script to add it.\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


