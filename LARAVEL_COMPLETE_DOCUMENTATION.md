# Laravel Backend - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Project Structure](#project-structure)
4. [Controllers](#controllers)
5. [API Endpoints](#api-endpoints)
6. [Routes](#routes)
7. [Authentication & Middleware](#authentication--middleware)
8. [Database & Models](#database--models)
9. [School Isolation](#school-isolation)
10. [Setup Instructions](#setup-instructions)
11. [Configuration](#configuration)
12. [Testing](#testing)
13. [Important Notes](#important-notes)

---

## Overview

LibraTrack has been converted to use **Laravel Framework (PHP 8)** as the backend, while maintaining the **HTML/CSS/JavaScript** frontend. This creates a clean separation of concerns with a RESTful API architecture.

### Key Features
- ✅ RESTful API endpoints (`/api/*`)
- ✅ Session-based authentication
- ✅ School-based data isolation
- ✅ Role-based access control (Admin, Librarian, Student)
- ✅ Clean controller architecture
- ✅ Proper middleware implementation

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend Layer                        │
│  HTML Files → JavaScript → Fetch API Calls              │
└────────────────────┬────────────────────────────────────┘
                     │ HTTP Requests
                     ▼
┌─────────────────────────────────────────────────────────┐
│                   Laravel Backend                        │
│  Routes → Middleware → Controllers → Database           │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                    Database (MySQL)                      │
│  users, books, loans, borrow_requests, etc.              │
└─────────────────────────────────────────────────────────┘
```

### Request Flow
1. **Frontend** makes API call: `fetch('/api/books')`
2. **Laravel Route** (`routes/api.php`) receives request
3. **Middleware** checks authentication/authorization
4. **Controller** processes request, queries database
5. **Response** returned as JSON
6. **Frontend** receives and displays data

---

## Project Structure

```
LibraTrack/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # All API controllers
│   │   │   ├── AuthController.php
│   │   │   ├── BookController.php
│   │   │   ├── LoanController.php
│   │   │   ├── BorrowRequestController.php
│   │   │   ├── UserController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── FavoriteController.php
│   │   │   ├── SchoolController.php
│   │   │   ├── LogController.php
│   │   │   ├── StudentController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── BaseController.php
│   │   │   └── Controller.php
│   │   └── Middleware/
│   │       ├── Authenticate.php
│   │       └── VerifyCsrfToken.php
│   ├── Models/                   # Eloquent models
│   │   ├── User.php
│   │   ├── Book.php
│   │   ├── Loan.php
│   │   └── ... (10 models)
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── RouteServiceProvider.php
│
├── routes/
│   ├── api.php                   # API endpoints
│   └── web.php                   # HTML page routes
│
├── config/
│   ├── app.php
│   └── database.php
│
├── public/
│   ├── assets/                   # CSS, JS, images
│   └── uploads/                  # User uploads
│
├── *.html                        # Frontend HTML files
└── backend/                      # OLD (can be deleted)
```

---

## Controllers

### BaseController
**Location**: `app/Http/Controllers/BaseController.php`

Provides common functionality for all controllers:
- `currentUser()` - Get current logged-in user from session
- `requireLogin()` - Require user to be authenticated
- `requireRole($roles)` - Require specific role(s)
- `loansHasSchoolId()` - Check if loans table has school_id column

### AuthController
**Location**: `app/Http/Controllers/AuthController.php`

**Methods**:
- `login(Request $request)` - Authenticate user
- `logout(Request $request)` - End session
- `checkAuth(Request $request)` - Verify authentication
- `me()` - Get current user info
- `register(Request $request)` - Create new user

**Features**:
- Password verification with rehashing
- Session management
- Email normalization

### BookController
**Location**: `app/Http/Controllers/BookController.php`

**Methods**:
- `list(Request $request)` - List books with pagination, search, filters
- `add(Request $request)` - Create new book
- `update(Request $request, $id)` - Update book
- `delete($id)` - Delete book

**Features**:
- School-filtered borrowed counts for librarians
- Favorite status for students
- Cover image upload handling

### LoanController
**Location**: `app/Http/Controllers/LoanController.php`

**Methods**:
- `list(Request $request)` - List loans with filters
- `create(Request $request)` - Create new loan
- `returnBook(Request $request)` - Mark book as returned
- `extendDueDate(Request $request)` - Extend loan due date
- `delete($id)` - Delete loan

**Features**:
- School isolation for librarians
- Status calculation (overdue, on-time, returned)
- Active/overdue filtering

### BorrowRequestController
**Location**: `app/Http/Controllers/BorrowRequestController.php`

**Methods**:
- `list(Request $request)` - List borrow requests
- `create(Request $request)` - Create borrow request (students)
- `approve(Request $request)` - Approve request (librarians)
- `reject(Request $request)` - Reject request (librarians)
- `countPending()` - Count pending requests

**Features**:
- School filtering for librarians
- Automatic loan creation on approval
- Book quantity management

### UserController
**Location**: `app/Http/Controllers/UserController.php`

**Methods**:
- `list(Request $request)` - List users with filters
- `createStudent(Request $request)` - Create student (librarians)
- `update(Request $request, $id)` - Update user
- `delete($id)` - Delete user (admins)
- `search(Request $request)` - Search students/books

**Features**:
- School filtering for librarians
- Role-based filtering
- Student number uniqueness per school

### CategoryController
**Location**: `app/Http/Controllers/CategoryController.php`

**Methods**:
- `list()` - List all categories
- `create(Request $request)` - Create category
- `update(Request $request, $id)` - Update category
- `delete(Request $request)` - Delete category

### FavoriteController
**Location**: `app/Http/Controllers/FavoriteController.php`

**Methods**:
- `list()` - List user's favorites
- `toggle(Request $request)` - Add/remove favorite

### SchoolController
**Location**: `app/Http/Controllers/SchoolController.php`

**Methods**:
- `list()` - List all schools

### LogController
**Location**: `app/Http/Controllers/LogController.php`

**Methods**:
- `list(Request $request)` - List logs with pagination

**Features**:
- School filtering for librarians

### StudentController
**Location**: `app/Http/Controllers/StudentController.php`

**Methods**:
- `myLoans(Request $request)` - Get student's loans
- `getBookBorrowingHistory(Request $request)` - Get book history

### SettingsController
**Location**: `app/Http/Controllers/SettingsController.php`

**Methods**:
- `get()` - Get all settings (admin only)
- `save(Request $request)` - Save settings (admin only)

---

## API Endpoints

### Authentication
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/login` | User login | No |
| POST | `/api/register` | User registration | No |
| POST | `/api/logout` | User logout | Yes |
| GET | `/api/check-auth` | Check authentication | Yes |
| GET | `/api/me` | Get current user | Yes |

### Books
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/books` | List books | No | - |
| POST | `/api/books` | Add book | Yes | Librarian, Admin |
| PUT | `/api/books/{id}` | Update book | Yes | Librarian, Admin |
| DELETE | `/api/books/{id}` | Delete book | Yes | Librarian, Admin |

### Loans
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/loans` | List loans | Yes | Librarian, Admin |
| POST | `/api/loans` | Create loan | Yes | Librarian, Admin |
| POST | `/api/loans/return` | Return book | Yes | Librarian, Admin |
| POST | `/api/loans/extend` | Extend due date | Yes | Librarian, Admin |
| DELETE | `/api/loans/{id}` | Delete loan | Yes | Librarian, Admin |

### Borrow Requests
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/borrow-requests` | List requests | Yes | Librarian, Admin |
| POST | `/api/borrow-requests` | Create request | Yes | Student |
| POST | `/api/borrow-requests/approve` | Approve request | Yes | Librarian, Admin |
| POST | `/api/borrow-requests/reject` | Reject request | Yes | Librarian, Admin |
| GET | `/api/borrow-requests/count-pending` | Count pending | Yes | - |

### Users
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/users` | List users | Yes | - |
| POST | `/api/users/students` | Create student | Yes | Librarian |
| PUT | `/api/users/{id}` | Update user | Yes | - |
| DELETE | `/api/users/{id}` | Delete user | Yes | Admin |
| GET | `/api/users/search` | Search students/books | Yes | Librarian, Admin |

### Categories
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/categories` | List categories | No | - |
| POST | `/api/categories` | Create category | Yes | Librarian, Admin |
| PUT | `/api/categories/{id}` | Update category | Yes | Librarian, Admin |
| DELETE | `/api/categories` | Delete category | Yes | Librarian, Admin |

### Favorites
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/favorites` | List favorites | Yes | Student |
| POST | `/api/favorites/toggle` | Toggle favorite | Yes | Student |

### Schools
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/schools` | List schools | Yes |

### Logs
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/logs` | List logs | Yes | Librarian, Admin |

### Student
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/student/my-loans` | Get student loans | Yes |
| GET | `/api/books/borrowing-history` | Get book history | Yes |

### Settings
| Method | Endpoint | Description | Auth Required | Roles |
|--------|----------|-------------|---------------|-------|
| GET | `/api/settings` | Get settings | Yes | Admin |
| POST | `/api/settings` | Save settings | Yes | Admin |

---

## Routes

### API Routes (`routes/api.php`)
All API endpoints are defined here. They use the `api` middleware group which:
- Excludes CSRF protection
- Uses API throttling
- Returns JSON responses

### Web Routes (`routes/web.php`)
Serves HTML files directly:
- Public pages: `/`, `/login`, `/register`, etc.
- Authenticated pages: `/dashboard`, `/books`, etc.
- Fallback route: `/{page}.html` for direct HTML access

**Example**:
```php
Route::get('/dashboard', function () {
    $html = file_get_contents(base_path('dashboard.html'));
    return response($html)->header('Content-Type', 'text/html');
})->middleware('auth');
```

---

## Authentication & Middleware

### Authenticate Middleware
**Location**: `app/Http/Middleware/Authenticate.php`

**Functionality**:
- Checks if user is logged in via session (`Session::has('uid')`)
- Validates role if specified
- Returns 401 for unauthenticated requests
- Returns 403 for unauthorized roles

**Usage**:
```php
Route::get('/api/books', [BookController::class, 'list'])->middleware('auth');
Route::get('/api/users', [UserController::class, 'list'])->middleware('auth:admin');
```

### VerifyCsrfToken Middleware
**Location**: `app/Http/Middleware/VerifyCsrfToken.php`

**Configuration**:
- Excludes `api/*` routes from CSRF protection
- Required for API-based architecture

### Session Management
- Uses Laravel's file-based sessions
- Session data stored in `storage/framework/sessions/`
- Session lifetime: 120 minutes (configurable)

---

## Database & Models

### Eloquent Models
Located in `app/Models/`:
- `User.php` - Users (admin, librarian, student)
- `Book.php` - Books
- `Loan.php` - Loans
- `BorrowRequest.php` - Borrow requests
- `Category.php` - Categories
- `Favorite.php` - User favorites
- `School.php` - Schools
- `Log.php` - Activity logs
- `Notification.php` - Notifications
- `Setting.php` - System settings

### Database Queries
Controllers use Laravel's Query Builder (`DB` facade) for:
- Complex joins
- School filtering
- Dynamic queries

**Example**:
```php
$query = DB::table('loans as l')
    ->join('books as b', 'b.id', '=', 'l.book_id')
    ->join('users as u', 'u.id', '=', 'l.student_id')
    ->where('l.status', 'borrowed');
```

---

## School Isolation

### Implementation
Librarians can only see/manage data from their assigned school. This is enforced in:

1. **User Listing** - Filtered by `school_id`
2. **Loan Listing** - Filtered by loan's `school_id` or student's `school_id`
3. **Borrow Requests** - Filtered by student's `school_id`
4. **Book Borrowed Counts** - Filtered by school
5. **All CRUD Operations** - Validated against librarian's school

### Key Methods
- `BaseController::requireRole()` - Checks user role
- School filtering in each controller's list methods
- Validation in create/update/delete methods

### Example (LoanController)
```php
if ($currentRole === 'librarian') {
    $librarianSchoolId = (int)($user['school_id'] ?? 0);
    if ($librarianSchoolId > 0) {
        if ($this->loansHasSchoolId()) {
            $query->where('l.school_id', $librarianSchoolId);
        } else {
            $query->where('u.school_id', $librarianSchoolId);
        }
    }
}
```

---

## Setup Instructions

### 1. Environment Configuration

Create `.env` file in root directory:

```env
APP_NAME=LibraTrack
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost/libratrack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=libratrack
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=120

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
```

### 2. Generate Application Key

```bash
php artisan key:generate
```

### 3. Set Permissions (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Web Server Configuration

**For Apache (XAMPP)**:
- Ensure `mod_rewrite` is enabled
- Create/update `.htaccess` in root (Laravel provides this)

**For Laravel Development Server**:
```bash
php artisan serve
# Access at http://localhost:8000
```

**For Production**:
- Point document root to `public/` directory
- Configure virtual host

### 5. Verify Setup

1. Check routes: `php artisan route:list`
2. Test API: `curl http://localhost/libratrack/api/categories`
3. Test login: `POST /api/login` with credentials

---

## Configuration

### Session Configuration
**File**: `config/session.php`

Key settings:
- `driver`: `file` (default)
- `lifetime`: `120` minutes
- `cookie`: `libratrack_session`

### Database Configuration
**File**: `config/database.php`

- Connection: MySQL
- Charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`

### CORS Configuration
If frontend is on different domain, configure CORS in:
- `config/cors.php`
- Or use `HandleCors` middleware

---

## Testing

### Manual Testing Checklist

#### Authentication
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Logout
- [ ] Check auth status
- [ ] Access protected route without auth

#### Books
- [ ] List books (public)
- [ ] Add book (librarian/admin)
- [ ] Update book
- [ ] Delete book
- [ ] Search books

#### Loans
- [ ] List loans (school-filtered for librarians)
- [ ] Create loan
- [ ] Return book
- [ ] Extend due date
- [ ] Delete loan

#### School Isolation
- [ ] Librarian sees only their school's data
- [ ] Librarian cannot create loan for other school
- [ ] Admin sees all data

### API Testing with cURL

```bash
# Login
curl -X POST http://localhost/libratrack/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Get books (with session cookie)
curl http://localhost/libratrack/api/books \
  -H "Cookie: libratrack_session=..."

# Create loan
curl -X POST http://localhost/libratrack/api/loans \
  -H "Content-Type: application/json" \
  -H "Cookie: libratrack_session=..." \
  -d '{"student_id":1,"book_id":1,"due_at":"2025-12-01"}'
```

---

## Important Notes

### 1. Old Backend Files
The `backend/` directory still exists but is **NOT USED**. You can safely delete it after verifying everything works.

### 2. CSRF Protection
- API routes (`/api/*`) are excluded from CSRF protection
- Web routes use CSRF tokens (if needed for forms)

### 3. Session Storage
- Sessions stored in `storage/framework/sessions/`
- Ensure directory is writable
- Clean old sessions periodically

### 4. Asset URLs
- Assets in `public/assets/`
- HTML files reference: `assets/css/main.css`
- Works if accessed via Laravel routes

### 5. Upload Handling
- Uploads stored in `public/uploads/`
- Cover images: `public/uploads/covers/`
- Ensure directory is writable

### 6. Error Handling
- Development: Errors shown (if `APP_DEBUG=true`)
- Production: Errors logged to `storage/logs/laravel.log`
- API errors return JSON format

### 7. School ID Column
- Some tables may not have `school_id` column
- Controllers check dynamically: `loansHasSchoolId()`
- Falls back to filtering via related tables

### 8. Password Hashing
- Uses Laravel's `Hash::make()`
- Automatically rehashes old passwords on login
- Supports legacy MD5/plain passwords (converts on login)

### 9. API Response Format
All API responses follow this format:
```json
{
  "ok": true,
  "data": {...},
  "message": "Success message"
}
```

Or for errors:
```json
{
  "ok": false,
  "error": "Error message",
  "field": "field_name" // optional
}
```

### 10. Frontend Integration
- Frontend JavaScript should use `/api/*` endpoints
- Include CSRF token for web routes (if using forms)
- Use `credentials: 'include'` for session cookies

---

## Troubleshooting

### Common Issues

**1. 404 on API routes**
- Check `.htaccess` is in root
- Verify `mod_rewrite` is enabled
- Check route exists: `php artisan route:list`

**2. Session not working**
- Check `storage/framework/sessions/` is writable
- Verify session driver in `.env`
- Clear cache: `php artisan config:clear`

**3. CSRF token mismatch**
- API routes are excluded (shouldn't happen)
- For web routes, include token in forms

**4. Database connection error**
- Check `.env` database credentials
- Verify MySQL is running
- Test connection: `php artisan tinker` then `DB::connection()->getPdo()`

**5. 500 Internal Server Error**
- Check `storage/logs/laravel.log`
- Verify `APP_DEBUG=true` in `.env` for details
- Check file permissions

---

## Summary

✅ **Backend**: 100% Laravel Framework  
✅ **Frontend**: HTML/CSS/JavaScript (preserved)  
✅ **API**: RESTful endpoints (`/api/*`)  
✅ **Authentication**: Session-based  
✅ **Authorization**: Role-based with school isolation  
✅ **Database**: MySQL with Laravel Query Builder  

Your application is production-ready! 🚀

What is Laravel?
Laravel is a PHP web framework for building web applications.
Overview
Laravel is an open-source PHP framework that provides:
Tools and libraries for common tasks
A structured way to organize code
Built-in features (authentication, routing, database, etc.)
Security best practices
A large community and ecosystem
Why use Laravel?
Before Laravel (your old code):
With Laravel (your new code):
Features
Routing
Define URLs and map them to code
Example: /api/books → BookController@list
Controllers
Organize request handling
Example: BookController handles all book operations
Database
Query builder and Eloquent ORM
Example: DB::table('books')->where('id', 1)->first()
Middleware
Run code before/after requests
Example: Check authentication before allowing access
Security
CSRF protection, SQL injection prevention, password hashing
Sessions
Built-in session management
Validation
Validate user input
Error handling
Structured error management
In your LibraTrack project
What changed
Before:
50+ PHP files in backend/ directory
Manual database connections
Manual session handling
Scattered code
After:
11 organized controllers
Clean API endpoints (/api/*)
Automatic session management
Structured, maintainable code
Example comparison
Old way (backend/list-loans.php):
New way (app/Http/Controllers/LoanController.php):
Benefits for your project
Organization
Controllers grouped by feature
Clear structure
Security
Built-in protections
Secure by default
Maintainability
Easier to update and fix
Less code duplication
Scalability
Handles growth
Performance optimizations
Community
Large ecosystem
Extensive documentation
Simple analogy
Think of building a house:
Without Laravel: Build everything from scratch (saw, hammer, nails)
With Laravel: Use a toolkit with pre-made components (power tools, pre-cut materials)
In summary
Laravel is a PHP framework that:
Makes development faster
Provides built-in features
Improves code organization
Enhances security
Simplifies maintenance
For LibraTrack, Laravel is the backend that powers your API, while your HTML/CSS/JavaScript frontend talks to it vi





