<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Student Book Visibility Check ===\n\n";

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

if (!$edmar || !$jane) {
    echo "❌ Librarians not found.\n";
    exit(1);
}

echo "Librarians:\n";
echo "  Edmar - ID: {$edmar->id}, School ID: {$edmar->school_id}\n";
echo "  Jane - ID: {$jane->id}, School ID: {$jane->school_id}\n\n";

// Get books by school
$edmarBooks = DB::table('books')
    ->where('school_id', $edmar->school_id)
    ->select('id', 'title', 'school_id', 'added_by')
    ->get();

$janeBooks = DB::table('books')
    ->where('school_id', $jane->school_id)
    ->select('id', 'title', 'school_id', 'added_by')
    ->get();

echo "Books in Edmar's school (school_id = {$edmar->school_id}):\n";
foreach ($edmarBooks as $book) {
    echo "  - ID {$book->id}: {$book->title} (added_by: {$book->added_by})\n";
}

echo "\nBooks in Jane's school (school_id = {$jane->school_id}):\n";
foreach ($janeBooks as $book) {
    echo "  - ID {$book->id}: {$book->title} (added_by: {$book->added_by})\n";
}

// Find students
$edmarStudents = DB::table('users')
    ->where('school_id', $edmar->school_id)
    ->where('role', 'student')
    ->select('id', 'first_name', 'last_name', 'school_id')
    ->get();

$janeStudents = DB::table('users')
    ->where('school_id', $jane->school_id)
    ->where('role', 'student')
    ->select('id', 'first_name', 'last_name', 'school_id')
    ->get();

echo "\nStudents in Edmar's school (school_id = {$edmar->school_id}):\n";
foreach ($edmarStudents as $student) {
    echo "  - {$student->first_name} {$student->last_name} (ID: {$student->id})\n";
    // What books should this student see?
    $studentBooks = DB::table('books')
        ->where('school_id', $student->school_id)
        ->count();
    echo "    Should see {$studentBooks} book(s)\n";
}

echo "\nStudents in Jane's school (school_id = {$jane->school_id}):\n";
foreach ($janeStudents as $student) {
    echo "  - {$student->first_name} {$student->last_name} (ID: {$student->id})\n";
    // What books should this student see?
    $studentBooks = DB::table('books')
        ->where('school_id', $student->school_id)
        ->count();
    echo "    Should see {$studentBooks} book(s)\n";
}

// Check for books with NULL school_id
$nullSchoolBooks = DB::table('books')->whereNull('school_id')->count();
if ($nullSchoolBooks > 0) {
    echo "\n⚠️  WARNING: {$nullSchoolBooks} book(s) have school_id = NULL\n";
    echo "These books will NOT be visible to any students!\n";
}


