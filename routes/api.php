<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\BorrowRequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (no auth middleware needed for login/register)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('web');
Route::get('/check-auth', [AuthController::class, 'checkAuth'])->middleware('web');
Route::get('/me', [AuthController::class, 'me'])->middleware('web');

// Book routes
Route::get('/books', [BookController::class, 'list']);
Route::get('/books/{id}', [BookController::class, 'show']); // Get single book
Route::post('/books', [BookController::class, 'add'])->middleware('auth');
Route::match(['put', 'post'], '/books/{id}', [BookController::class, 'update'])->middleware('auth');
Route::delete('/books/{id}', [BookController::class, 'delete'])->middleware('auth');

// Loan routes
Route::get('/loans', [LoanController::class, 'list'])->middleware('auth');
Route::post('/loans', [LoanController::class, 'create'])->middleware('auth');
Route::post('/loans/return', [LoanController::class, 'returnBook'])->middleware('auth');
Route::post('/loans/extend', [LoanController::class, 'extendDueDate'])->middleware('auth');
Route::delete('/loans/{id}', [LoanController::class, 'delete'])->middleware('auth');

// Borrow request routes
Route::get('/borrow-requests', [BorrowRequestController::class, 'list'])->middleware('auth');
Route::post('/borrow-requests', [BorrowRequestController::class, 'create'])->middleware('auth');
Route::post('/borrow-requests/approve', [BorrowRequestController::class, 'approve'])->middleware('auth');
Route::post('/borrow-requests/reject', [BorrowRequestController::class, 'reject'])->middleware('auth');
Route::get('/borrow-requests/count-pending', [BorrowRequestController::class, 'countPending'])->middleware('auth');

// User routes - Specific routes must come before parameterized routes
Route::get('/users', [UserController::class, 'list'])->middleware('auth');
Route::get('/users/search', [UserController::class, 'search'])->middleware('auth');
Route::post('/users/students', [UserController::class, 'createStudent'])->middleware('auth');
Route::post('/users/librarians', [UserController::class, 'createLibrarian'])->middleware('auth');
Route::put('/users/{id}', [UserController::class, 'update'])->middleware('auth');
Route::delete('/users/{id}', [UserController::class, 'delete'])->middleware('auth');

// Category routes
Route::get('/categories', [CategoryController::class, 'list']); // Public - everyone needs to see categories
Route::post('/categories', [CategoryController::class, 'create'])->middleware('auth');
Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('auth');
Route::delete('/categories', [CategoryController::class, 'delete'])->middleware('auth');

// Favorite routes
Route::get('/favorites', [FavoriteController::class, 'list'])->middleware('auth');
Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->middleware('auth');

// School routes
Route::get('/schools', [SchoolController::class, 'list'])->middleware('auth');
Route::get('/admin/schools', [SchoolController::class, 'adminList'])->middleware('auth');
Route::post('/admin/schools', [SchoolController::class, 'create'])->middleware('auth');
Route::put('/admin/schools/{id}', [SchoolController::class, 'update'])->middleware('auth');
Route::put('/admin/schools/{id}/status', [SchoolController::class, 'setStatus'])->middleware('auth');

// Log routes - Admin only
Route::get('/logs', [LogController::class, 'list'])->middleware('auth');
Route::get('/api/system_logs_list', [LogController::class, 'list'])->middleware('auth');

// Student routes
Route::get('/student/my-loans', [StudentController::class, 'myLoans'])->middleware('web');
Route::get('/student/notifications', [StudentController::class, 'notifications'])->middleware('web');
Route::get('/books/borrowing-history', [StudentController::class, 'getBookBorrowingHistory'])->middleware('web');

// Settings routes
Route::get('/settings', [SettingsController::class, 'get'])->middleware('auth');
Route::post('/settings', [SettingsController::class, 'save'])->middleware('auth');
Route::post('/settings/test-smtp', [SettingsController::class, 'testSmtp'])->middleware('auth');

// Profile routes
Route::post('/profile/avatar', [UserController::class, 'uploadAvatar'])->middleware('auth');

// Notification routes
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('auth');
Route::get('/notifications/list', [NotificationController::class, 'list'])->middleware('auth');
Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->middleware('auth');
Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->middleware('auth');

// Email queue processing (admin only)
Route::post('/admin/process-email-queue', [NotificationController::class, 'processEmailQueue'])->middleware('auth');

