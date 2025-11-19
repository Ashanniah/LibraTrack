# Laravel Conversion Guide for LibraTrack

This document outlines the conversion from vanilla PHP to Laravel while maintaining all existing functionality.

## Conversion Status

✅ Laravel Framework Installed
✅ Core Laravel Structure Created
🔄 Database Migrations (In Progress)
🔄 Eloquent Models (In Progress)
🔄 Controllers (In Progress)
🔄 Blade Templates (In Progress)
🔄 Routes (In Progress)

## Key Conversion Patterns

### 1. Database Queries → Eloquent Models

**Before (MySQLi):**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
```

**After (Laravel/Eloquent):**
```php
$user = User::find($id);
// or
$user = User::where('id', $id)->first();
```

### 2. JSON Responses

**Before:**
```php
json_response(['success' => true, 'data' => $data], 200);
```

**After:**
```php
return response()->json(['success' => true, 'data' => $data], 200);
```

### 3. Authentication

**Before:**
```php
if (empty($_SESSION['uid'])) jsonError('Unauthorized', 401);
$user = current_user($conn);
```

**After:**
```php
use Illuminate\Support\Facades\Auth;

if (!Auth::check()) {
    return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
}
$user = Auth::user();
```

### 4. Request Data

**Before:**
```php
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($_POST['email'] ?? '');
```

**After:**
```php
use Illuminate\Http\Request;

public function store(Request $request) {
    $email = $request->input('email');
    // or
    $data = $request->all();
}
```

### 5. Validation

**Before:**
```php
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email', 422, 'email');
}
```

**After:**
```php
$request->validate([
    'email' => 'required|email',
]);
```

## File Structure Mapping

| Old Location | New Location |
|-------------|--------------|
| `backend/*.php` | `app/Http/Controllers/*.php` |
| `*.html` | `resources/views/*.blade.php` |
| `assets/` | `public/assets/` |
| `backend/db.php` | `config/database.php` (Laravel handles this) |
| `backend/auth.php` | `app/Http/Middleware/Authenticate.php` |

## Next Steps

1. Create all database migrations
2. Create Eloquent models for all tables
3. Convert all backend PHP files to controllers
4. Convert all HTML files to Blade templates
5. Set up routes in `routes/web.php` and `routes/api.php`
6. Move assets to `public/` directory
7. Update all JavaScript fetch URLs to use Laravel routes
8. Test all functionality

## Important Notes

- All existing functionality must be preserved
- School isolation logic must be maintained
- Session-based authentication converted to Laravel sessions
- All API endpoints should return the same JSON structure
- Frontend JavaScript should work with minimal changes








