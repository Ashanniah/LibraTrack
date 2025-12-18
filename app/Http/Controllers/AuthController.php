<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\AuditLog;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100|regex:/^[\p{L}\p{M}\s\.\-\']+$/u',
            'last_name' => 'required|string|max:100|regex:/^[\p{L}\p{M}\s\.\-\']+$/u',
            'middle_name' => 'nullable|string|max:100|regex:/^[\p{L}\p{M}\s\.\-\']+$/u',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value) || !preg_match('/[^A-Za-z0-9]/', $value)) {
                        $fail('Password must be 8+ chars with 1 uppercase & 1 special character.');
                    }
                },
            ],
            'confirm_password' => 'required|same:password',
            'role' => 'nullable|in:student,librarian,admin',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstError = $errors->first();
            $firstField = $errors->keys()[0] ?? null;
            
            return response()->json([
                'success' => false,
                'message' => $firstError,
                'field' => $firstField
            ], 422);
        }

        // Normalize email
        $email = trim($request->email);
        $email = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00A0}\s]+/u', '', $email);
        if (strpos($email, '@') !== false) {
            [$local, $domain] = explode('@', $email, 2);
            if ($domain !== '' && function_exists('idn_to_ascii')) {
                $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT);
                if ($asciiDomain) {
                    $email = $local . '@' . $asciiDomain;
                }
            }
        }

        // Check if email already exists (after normalization)
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already registered',
                'field' => 'email'
            ], 409);
        }

        // Start PHP native session if not already started
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
        
        // Determine role
        $role = 'student';
        $isAdmin = strtolower($_SESSION['role'] ?? '') === 'admin';
        if ($isAdmin && in_array($request->role, ['student', 'librarian', 'admin'], true)) {
            $role = $request->role;
        }

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name ?: null,
            'last_name' => $request->last_name,
            'suffix' => $request->suffix ?: null,
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Auto-login on self-registration
        if (empty($_SESSION['uid'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $user->id;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
            $_SESSION['first_name'] = $user->first_name;
            $_SESSION['last_name'] = $user->last_name;
            $_SESSION['logged_in'] = time();
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'id' => $user->id,
            'role' => $role
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid email or password',
                'field' => 'password'
            ], 401);
        }

        $email = trim($request->email);
        $user = User::where('email', $email)->first();

        if (!$user) {
            usleep(250000); // Prevent timing attacks
            return response()->json([
                'success' => false,
                'error' => 'Invalid email or password',
                'field' => 'password'
            ], 401);
        }

        if (strtolower($user->status) !== 'active') {
            return response()->json([
                'success' => false,
                'error' => 'Account is disabled'
            ], 403);
        }

        // Verify password - handle multiple formats (bcrypt, plain text, MD5)
        $stored = trim($user->password);
        $ok = false;
        $info = password_get_info($stored);

        // Check if it's a bcrypt hash
        if ($stored !== '' && $info['algo'] !== 0) {
            $ok = Hash::check($request->password, $stored);
            if ($ok && Hash::needsRehash($stored)) {
                $user->password = Hash::make($request->password);
                $user->save();
            }
        } 
        // Check if it's plain text (legacy)
        elseif ($stored !== '' && hash_equals($stored, $request->password)) {
            $ok = true;
            $user->password = Hash::make($request->password);
            $user->save();
        } 
        // Check if it's MD5 (legacy)
        elseif ($stored !== '' && hash_equals($stored, md5($request->password))) {
            $ok = true;
            $user->password = Hash::make($request->password);
            $user->save();
        }

        if (!$ok) {
            usleep(250000);
            
            // Log failed login attempt
            $actor = [
                'id' => null,
                'first_name' => 'Unknown',
                'last_name' => 'User',
                'role' => 'guest',
                'email' => $email
            ];
            AuditLog::logAction(
                $actor,
                'LOGIN_FAIL',
                'auth',
                null,
                "Failed login attempt for email: {$email}"
            );
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid email or password',
                'field' => 'password'
            ], 401);
        }

        // Start PHP native session if not already started
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
        
        // Set session
        session_regenerate_id(true);
        $_SESSION['uid'] = $user->id;
        $_SESSION['role'] = strtolower($user->role);
        $_SESSION['email'] = $user->email;
        $_SESSION['first_name'] = $user->first_name;
        $_SESSION['last_name'] = $user->last_name;
        $_SESSION['logged_in'] = time();

        // Log successful login
        $actor = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => strtolower($user->role),
            'email' => $user->email
        ];
        AuditLog::logAction(
            $actor,
            'LOGIN_SUCCESS',
            'auth',
            $user->id,
            "User logged in successfully"
        );

        return response()->json([
            'success' => true,
            'redirect' => '/dashboard.html',
            'role' => strtolower($user->role),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => strtolower($user->role),
            ],
            'session_id' => session_id(),
        ]);
    }

    public function logout()
    {
        // Start PHP native session if not already started
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
        
        // Log logout before destroying session
        if (!empty($_SESSION['uid'])) {
            $actor = [
                'id' => $_SESSION['uid'],
                'first_name' => $_SESSION['first_name'] ?? '',
                'last_name' => $_SESSION['last_name'] ?? '',
                'role' => $_SESSION['role'] ?? 'unknown',
                'email' => $_SESSION['email'] ?? ''
            ];
            AuditLog::logAction(
                $actor,
                'LOGOUT',
                'auth',
                $_SESSION['uid'],
                "User logged out"
            );
        }
        
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        return response()->json(['success' => true]);
    }

    public function checkAuth()
    {
        // Start PHP native session if not already started
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
        
        if (empty($_SESSION['uid'])) {
            return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        // Use direct DB query to ensure we get the absolute latest data from database (bypass Eloquent cache)
        $userId = $_SESSION['uid'];
        $userData = DB::table('users')->where('id', $userId)->first();
        
        if (!$userData || strtolower($userData->status ?? '') !== 'active') {
            $_SESSION = [];
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            session_destroy();
            return response()->json(['success' => false, 'error' => 'Not authenticated'], 401);
        }
        
        // Note: Using direct DB query ($userData) instead of Eloquent to ensure fresh data

        // Get school name if school_id exists
        $schoolName = null;
        if ($userData->school_id ?? null) {
            try {
                $school = DB::table('schools')
                    ->where('id', $userData->school_id)
                    ->value('name');
                if ($school) {
                    $schoolName = $school;
                }
            } catch (\Throwable $e) {
                // If schools table doesn't exist or error, just set to null
                $schoolName = null;
            }
        }

        // Get avatar if column exists - use try-catch to handle gracefully
        $avatar = $userData->avatar ?? null;
        if (empty($avatar)) {
            $avatar = null;
        }

        // Safely get created_at
        $createdAt = null;
        try {
            if (isset($userData->created_at) && $userData->created_at) {
                $createdAt = is_string($userData->created_at) ? $userData->created_at : $userData->created_at;
            }
        } catch (\Exception $e) {
            $createdAt = null;
        }

        // Get phone and address if columns exist
        $phone = $userData->phone ?? null;
        $address = $userData->address ?? null;

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $userData->id,
                'first_name' => $userData->first_name ?? '',
                'last_name' => $userData->last_name ?? '',
                'middle_name' => $userData->middle_name ?? null,
                'suffix' => $userData->suffix ?? null,
                'email' => $userData->email ?? '',
                'role' => strtolower($userData->role ?? 'student'),
                'school_id' => $userData->school_id ?? null,
                'school_name' => $schoolName,
                'status' => $userData->status ?? 'active',
                'created_at' => $createdAt,
                'avatar' => $avatar,
                'phone' => $phone,
                'address' => $address,
            ]
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }
}
