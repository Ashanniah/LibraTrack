<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\BaseController;

echo "=== Testing booksHasAddedBy() Method ===\n\n";

// Create a test controller instance to test the method
class TestController extends \App\Http\Controllers\BaseController {
    public function testBooksHasAddedBy() {
        return $this->booksHasAddedBy();
    }
}

$controller = new TestController();
$result = $controller->testBooksHasAddedBy();

echo "booksHasAddedBy() returns: " . ($result ? "TRUE" : "FALSE") . "\n\n";

// Direct check
$columns = DB::select("SHOW COLUMNS FROM books LIKE 'added_by'");
echo "Direct DB check: " . (count($columns) > 0 ? "Column EXISTS" : "Column MISSING") . "\n";

if (count($columns) > 0) {
    echo "Column details:\n";
    foreach ($columns as $col) {
        echo "  Field: " . $col->Field . "\n";
        echo "  Type: " . $col->Type . "\n";
        echo "  Null: " . $col->Null . "\n";
    }
}


