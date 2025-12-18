<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Serve static assets from public directory (for /libratrack/assets/ compatibility)
Route::get('/libratrack/assets/{path}', function ($path) {
    $filePath = public_path('assets/' . $path);
    if (File::exists($filePath)) {
        $mimeType = File::mimeType($filePath);
        return response()->file($filePath, ['Content-Type' => $mimeType]);
    }
    abort(404);
})->where('path', '.*');

// Serve assets from root (for direct /assets/ access)
Route::get('/assets/{path}', function ($path) {
    $filePath = public_path('assets/' . $path);
    if (File::exists($filePath)) {
        $mimeType = File::mimeType($filePath);
        return response()->file($filePath, ['Content-Type' => $mimeType]);
    }
    abort(404);
})->where('path', '.*');

// Public pages - serve HTML files directly
Route::get('/', function () {
    $html = file_get_contents(base_path('index.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/index.html', function () {
    $html = file_get_contents(base_path('index.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/login', function () {
    $html = file_get_contents(base_path('login.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/login.html', function () {
    $html = file_get_contents(base_path('login.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/register', function () {
    $html = file_get_contents(base_path('register.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/register.html', function () {
    $html = file_get_contents(base_path('register.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/forgot', function () {
    $html = file_get_contents(base_path('forgot.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/reset-code', function () {
    $html = file_get_contents(base_path('reset-code.html'));
    $html = injectBaseTag($html, '/');
    return response($html)->header('Content-Type', 'text/html');
});

// Helper function to inject base tag for asset paths
function injectBaseTag($html, $basePath = '/') {
    // Inject base tag right after <head> tag
    if (strpos($html, '<head>') !== false) {
        $html = str_replace('<head>', '<head><base href="' . $basePath . '">', $html);
    } elseif (strpos($html, '<head ') !== false) {
        // Handle <head with attributes
        $html = preg_replace('/(<head[^>]*>)/', '$1<base href="' . $basePath . '">', $html);
    }
    return $html;
}

// Authenticated pages - serve HTML files directly
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $html = file_get_contents(base_path('dashboard.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/dashboard.html', function () {
        $html = file_get_contents(base_path('dashboard.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    // Handle /libratrack/ prefix for backward compatibility
    Route::get('/libratrack/dashboard.html', function () {
        $html = file_get_contents(base_path('dashboard.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/books', function () {
        $html = file_get_contents(base_path('books.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/favorites', function () {
        $html = file_get_contents(base_path('favorites.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    // Student pages
    Route::get('/student/search', function () {
        $html = file_get_contents(base_path('student-search.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/student/favorite', function () {
        $html = file_get_contents(base_path('student-favorite.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/student/history', function () {
        $html = file_get_contents(base_path('student-history.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/student/overdue', function () {
        $html = file_get_contents(base_path('student-overdue.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/student/notifications', function () {
        $html = file_get_contents(base_path('student-notifications.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/student/profile', function () {
        $html = file_get_contents(base_path('student-profile.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    // Librarian pages
    Route::get('/librarian/books', function () {
        $html = file_get_contents(base_path('librarian-books.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/add-book', function () {
        $html = file_get_contents(base_path('librarian-add-book.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/borrow-requests', function () {
        $html = file_get_contents(base_path('librarian-borrow-requests.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/users', function () {
        $html = file_get_contents(base_path('librarian-users.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/active-loans', function () {
        $html = file_get_contents(base_path('librarian-active-loans.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/overdue', function () {
        $html = file_get_contents(base_path('librarian-overdue.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/history', function () {
        $html = file_get_contents(base_path('librarian-history.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/lowstock', function () {
        $html = file_get_contents(base_path('librarian-lowstock.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/profile', function () {
        $html = file_get_contents(base_path('librarian-profile.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/librarian/emails', function () {
        $html = file_get_contents(base_path('librarian-emails.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/borrow-return', function () {
        $html = file_get_contents(base_path('borrow-return.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    // Admin pages
    Route::get('/admin/users', function () {
        $html = file_get_contents(base_path('admin-users.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/admin/categories', function () {
        $html = file_get_contents(base_path('admin-categories.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/admin/logs', function () {
        $html = file_get_contents(base_path('admin-logs.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/admin/settings', function () {
        $html = file_get_contents(base_path('admin-settings.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });
    Route::get('/admin/schools', function () {
        $html = file_get_contents(base_path('admin-schools.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/admin/logs', function () {
        $html = file_get_contents(base_path('admin-logs.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });

    Route::get('/admin/profile', function () {
        $html = file_get_contents(base_path('admin-profile.html'));
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    });
    
    // Utility scripts for book assignment
    Route::match(['get', 'post'], '/assign-all-books-to-librarians', [App\Http\Controllers\BookAssignmentController::class, 'assignAllBooks']);
});

// Handle /libratrack/ prefix for all HTML files (backward compatibility)
Route::get('/libratrack/{page}.html', function ($page) {
    $file = base_path($page . '.html');
    if (file_exists($file)) {
        $html = file_get_contents($file);
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    }
    abort(404);
})->where('page', '[a-z0-9-]+');

// Fallback: serve HTML files directly if they exist (for direct access)
Route::get('/{page}.html', function ($page) {
    $file = base_path($page . '.html');
    if (file_exists($file)) {
        $html = file_get_contents($file);
        $html = injectBaseTag($html, '/');
        return response($html)->header('Content-Type', 'text/html');
    }
    abort(404);
})->where('page', '[a-z0-9-]+');
