<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends BaseController
{
    public function list(Request $request)
    {
        $user = $this->requireLogin();

        $roleFilter = strtolower(trim($request->input('role', '')));
        $q = trim($request->input('q', ''));
        $limit = max(1, min(200, (int)$request->input('limit', 100)));
        $offset = max(0, (int)$request->input('offset', 0));

        $query = DB::table('users as u')
            ->leftJoin('schools as s', 's.id', '=', 'u.school_id');

        // School filtering for librarians
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            if ($librarianSchoolId > 0) {
                $query->where('u.school_id', $librarianSchoolId);
            } else {
                return response()->json(['success' => true, 'users' => [], 'total' => 0], 200);
            }
        }

        if ($roleFilter !== '') {
            $query->whereRaw('LOWER(u.role) = ?', [strtolower($roleFilter)]);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function($qry) use ($like) {
                $qry->where('u.first_name', 'LIKE', $like)
                    ->orWhere('u.last_name', 'LIKE', $like)
                    ->orWhere('u.email', 'LIKE', $like)
                    ->orWhere('u.student_no', 'LIKE', $like);
            });
        }

        $users = $query->select([
                'u.id',
                'u.first_name',
                'u.middle_name',
                'u.last_name',
                'u.suffix',
                'u.email',
                'u.role',
                'u.status',
                'u.student_no',
                'u.course',
                'u.year_level',
                'u.section',
                'u.notes',
                'u.school_id',
                's.name as school_name',
                'u.created_at'
            ])
            ->orderBy('u.created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        if ($roleFilter === 'student') {
            return response()->json(['success' => true, 'students' => $users], 200);
        }

        return response()->json(['success' => true, 'users' => $users], 200);
    }

    public function createStudent(Request $request)
    {
        $user = $this->requireRole(['librarian']);

        // Get librarian's school_id
        $librarian = DB::table('users')->where('id', $user['id'])->first();
        $schoolId = (int)($librarian->school_id ?? 0);
        if ($schoolId <= 0) {
            return response()->json(['success' => false, 'message' => 'Librarian must be assigned to a school.'], 400);
        }

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'student_no' => 'required|string|regex:/^\d{8}$/',
            'course' => 'required|string',
            'year_level' => 'required|string',
            'section' => 'required|string',
            'status' => 'required|string',
            'password' => ['required', 'min:8', 'regex:/[A-Z]/', 'regex:/[^a-zA-Z0-9]/'],
            'middle_name' => 'nullable|string',
            'suffix' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Check unique email
        if (DB::table('users')->where('email', $request->input('email'))->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered.', 'field' => 'email'], 409);
        }

        // Check unique student_no within school
        if (DB::table('users')->where('student_no', $request->input('student_no'))->where('school_id', $schoolId)->exists()) {
            return response()->json(['success' => false, 'message' => 'This Student No. already exists in this school.', 'field' => 'student_no'], 409);
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name') ?: null,
            'last_name' => $request->input('last_name'),
            'suffix' => $request->input('suffix') ?: null,
            'email' => strtolower(trim($request->input('email'))),
            'password' => Hash::make($request->input('password')),
            'student_no' => $request->input('student_no'),
            'course' => $request->input('course'),
            'year_level' => $request->input('year_level'),
            'section' => $request->input('section'),
            'status' => strtolower($request->input('status')),
            'notes' => $request->input('notes') ?: null,
            'role' => 'student',
            'school_id' => $schoolId,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $userId]);
    }

    public function createLibrarian(Request $request)
    {
        $user = $this->requireRole(['admin']);

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'min:8', 'regex:/[A-Z]/', 'regex:/[^a-zA-Z0-9]/'],
            'status' => 'required|string|in:active,disabled',
            'school_id' => 'required|integer|exists:schools,id',
            'middle_name' => 'nullable|string',
            'suffix' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Check unique email
        if (DB::table('users')->where('email', strtolower(trim($request->input('email'))))->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered.', 'field' => 'email'], 409);
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name') ?: null,
            'last_name' => $request->input('last_name'),
            'suffix' => $request->input('suffix') ?: null,
            'email' => strtolower(trim($request->input('email'))),
            'password' => Hash::make($request->input('password')),
            'status' => strtolower($request->input('status')),
            'notes' => $request->input('notes') ?: null,
            'role' => 'librarian',
            'school_id' => $request->input('school_id'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $userId, 'message' => 'Librarian account created successfully!']);
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireLogin();

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'status' => 'required|string',
            'middle_name' => 'nullable|string',
            'suffix' => 'nullable|string',
            'student_no' => 'nullable|string',
            'course' => 'nullable|string',
            'year_level' => 'nullable|string',
            'section' => 'nullable|string',
            'notes' => 'nullable|string',
            'school_id' => 'nullable|integer',
            'password' => 'nullable|min:8',
        ]);

        $updateData = [
            'first_name' => $request->input('first_name'),
            'middle_name' => $request->input('middle_name') ?: null,
            'last_name' => $request->input('last_name'),
            'suffix' => $request->input('suffix') ?: null,
            'email' => $request->input('email'),
            'status' => $request->input('status'),
            // Note: updated_at column doesn't exist in the users table
        ];

        if ($request->has('student_no')) {
            $updateData['student_no'] = $request->input('student_no');
        }
        if ($request->has('course')) {
            $updateData['course'] = $request->input('course');
        }
        if ($request->has('year_level')) {
            $updateData['year_level'] = $request->input('year_level');
        }
        if ($request->has('section')) {
            $updateData['section'] = $request->input('section');
        }
        if ($request->has('notes')) {
            $updateData['notes'] = $request->input('notes');
        }
        if ($request->has('school_id') && $user['role'] === 'admin') {
            $updateData['school_id'] = $request->input('school_id');
        }
        if ($request->has('password') && $request->input('password')) {
            $updateData['password'] = Hash::make($request->input('password'));
        }

        $updated = DB::table('users')->where('id', $id)->update($updateData);
        
        if ($updated === false) {
            return response()->json(['success' => false, 'message' => 'Failed to update user'], 500);
        }

        // Verify the update was successful by fetching the updated record
        $updatedUser = DB::table('users')->where('id', $id)->first();
        if (!$updatedUser) {
            return response()->json(['success' => false, 'message' => 'User not found after update'], 500);
        }

        return response()->json([
            'success' => true, 
            'message' => 'User updated successfully',
            'user' => [
                'id' => $updatedUser->id,
                'first_name' => $updatedUser->first_name ?? '',
                'last_name' => $updatedUser->last_name ?? '',
                'email' => $updatedUser->email ?? '',
            ]
        ]);
    }

    public function delete($id)
    {
        $user = $this->requireRole(['admin']);

        DB::table('users')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    public function search(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $q = trim($request->input('q', ''));
        $type = trim($request->input('type', 'both')); // 'students', 'books', or 'both'

        if (!$q) {
            return response()->json(['ok' => true, 'students' => [], 'books' => []]);
        }

        $results = ['students' => [], 'books' => []];

        // Search students
        if ($type === 'both' || $type === 'students') {
            $query = DB::table('users')
                ->where('role', 'student')
                ->where(function($qry) use ($q) {
                    $qry->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"])
                        ->orWhere('student_no', 'LIKE', "%{$q}%")
                        ->orWhere('email', 'LIKE', "%{$q}%");
                })
                ->limit(20);

            // School filtering for librarians
            $currentRole = strtolower($user['role'] ?? '');
            if ($currentRole === 'librarian') {
                $librarianSchoolId = (int)($user['school_id'] ?? 0);
                if ($librarianSchoolId > 0) {
                    $query->where('school_id', $librarianSchoolId);
                } else {
                    return response()->json(['ok' => true, 'students' => [], 'books' => []]);
                }
            }

            $students = $query->select('id', 'first_name', 'last_name', 'student_no', 'email')->get();
            foreach ($students as $student) {
                $results['students'][] = [
                    'id' => (int)$student->id,
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'student_no' => $student->student_no ?? '',
                    'email' => $student->email ?? '',
                    'display' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) . ' (' . ($student->student_no ?? '') . ')'
                ];
            }
        }

        // Search books
        if ($type === 'both' || $type === 'books') {
            $books = DB::table('books')
                ->where(function($qry) use ($q) {
                    $qry->where('title', 'LIKE', "%{$q}%")
                        ->orWhere('author', 'LIKE', "%{$q}%")
                        ->orWhere('isbn', 'LIKE', "%{$q}%")
                        ->orWhere('category', 'LIKE', "%{$q}%");
                })
                ->select('id', 'title', 'author', 'isbn', 'category')
                ->limit(20)
                ->get();

            foreach ($books as $book) {
                $results['books'][] = [
                    'id' => (int)$book->id,
                    'title' => $book->title ?? '',
                    'author' => $book->author ?? '',
                    'isbn' => $book->isbn ?? '',
                    'category' => $book->category ?? '',
                    'display' => ($book->title ?? '') . ' — ' . ($book->isbn ?? '')
                ];
            }
        }

        return response()->json(['ok' => true, 'students' => $results['students'], 'books' => $results['books']]);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $this->requireLogin();

        if (!$request->hasFile('avatar')) {
            return response()->json(['success' => false, 'message' => 'No avatar file provided'], 400);
        }

        $file = $request->file('avatar');
        
        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => 'File too large (max 5MB)'], 400);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF are allowed.'], 400);
        }

        // Create uploads directory if it doesn't exist
        $uploadDir = public_path('uploads/avatars');
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                return response()->json(['success' => false, 'message' => 'Failed to create upload directory'], 500);
            }
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = 'avatar_' . $user['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!$file->move($uploadDir, $filename)) {
            return response()->json(['success' => false, 'message' => 'Failed to save avatar'], 500);
        }

        // Set file permissions
        @chmod($filepath, 0644);

        // Update user record with avatar path
        $avatarUrl = '/uploads/avatars/' . $filename;
        try {
            // Update avatar in database
            DB::table('users')->where('id', $user['id'])->update(['avatar' => $avatarUrl]);
        } catch (\Exception $e) {
            // If column doesn't exist, create it first
            try {
                DB::statement("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER status");
                DB::table('users')->where('id', $user['id'])->update(['avatar' => $avatarUrl]);
            } catch (\Exception $e2) {
                // If still fails, log but don't break
                \Log::warning('Failed to save avatar to database: ' . $e2->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $avatarUrl
        ]);
    }
}




