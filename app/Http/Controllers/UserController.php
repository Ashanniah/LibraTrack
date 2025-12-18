<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Helpers\AuditLog;

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

        // Validate school exists and is active
        $school = DB::table('schools')->where('id', $schoolId)->first();
        if (!$school) {
            return response()->json(['success' => false, 'message' => 'Invalid school assigned to librarian.'], 400);
        }

        // Normalize inputs
        $firstName = trim($request->input('first_name', ''));
        $middleName = trim($request->input('middle_name', ''));
        $lastName = trim($request->input('last_name', ''));
        $suffix = trim($request->input('suffix', ''));
        $email = strtolower(trim($request->input('email', '')));
        $studentNo = trim($request->input('student_no', ''));
        $course = trim($request->input('course', ''));
        $yearLevel = trim($request->input('year_level', ''));
        $section = trim($request->input('section', ''));
        $status = strtolower(trim($request->input('status', 'active')));
        $password = $request->input('password', '');

        // 🧍 Personal Info Validation
        if (empty($firstName)) {
            return response()->json(['success' => false, 'message' => 'First name is required.', 'field' => 'first_name'], 422);
        }
        if (strlen($firstName) < 2 || strlen($firstName) > 50) {
            return response()->json(['success' => false, 'message' => 'First name must be 2-50 characters.', 'field' => 'first_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $firstName)) {
            return response()->json(['success' => false, 'message' => 'First name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'first_name'], 422);
        }

        if (!empty($middleName)) {
            if (strlen($middleName) < 2 || strlen($middleName) > 50) {
                return response()->json(['success' => false, 'message' => 'Middle name must be 2-50 characters.', 'field' => 'middle_name'], 422);
            }
            if (!preg_match('/^[a-zA-Z\s\-\']+$/', $middleName)) {
                return response()->json(['success' => false, 'message' => 'Middle name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'middle_name'], 422);
            }
        }

        if (empty($lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name is required.', 'field' => 'last_name'], 422);
        }
        if (strlen($lastName) < 2 || strlen($lastName) > 50) {
            return response()->json(['success' => false, 'message' => 'Last name must be 2-50 characters.', 'field' => 'last_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'last_name'], 422);
        }

        $allowedSuffixes = ['', 'N/A', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        if (!empty($suffix) && !in_array($suffix, $allowedSuffixes)) {
            return response()->json(['success' => false, 'message' => 'Invalid suffix. Please select from the allowed values.', 'field' => 'suffix'], 422);
        }

        // 📧 Account & Status Validation
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Email is required.', 'field' => 'email'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Enter a valid email address.', 'field' => 'email'], 422);
        }

        if (!in_array($status, ['active', 'disabled', 'inactive'])) {
            return response()->json(['success' => false, 'message' => 'Status must be Active or Disabled.', 'field' => 'status'], 422);
        }

        if (empty($password)) {
            return response()->json(['success' => false, 'message' => 'Password is required.', 'field' => 'password'], 422);
        }
        if (strlen($password) < 8) {
            return response()->json(['success' => false, 'message' => 'Password must be at least 8 characters.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 uppercase letter.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[a-z]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 lowercase letter.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 number.', 'field' => 'password'], 422);
        }
        // Password must NOT be identical to email or student ID
        if (strtolower($password) === strtolower($email)) {
            return response()->json(['success' => false, 'message' => 'Password cannot be the same as your email.', 'field' => 'password'], 422);
        }
        if ($password === $studentNo) {
            return response()->json(['success' => false, 'message' => 'Password cannot be the same as your student ID.', 'field' => 'password'], 422);
        }

        // 🏫 Academic Info Validation
        if (empty($studentNo)) {
            return response()->json(['success' => false, 'message' => 'School ID / Student No. is required.', 'field' => 'student_no'], 422);
        }
        if (!preg_match('/^\d{8}$/', $studentNo)) {
            return response()->json(['success' => false, 'message' => 'Student number must be exactly 8 digits.', 'field' => 'student_no'], 422);
        }

        if (empty($course)) {
            return response()->json(['success' => false, 'message' => 'Course is required.', 'field' => 'course'], 422);
        }
        // Validate course is from allowed list
        $allowedCourses = [
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Information Systems',
            'Bachelor of Science in Computer Engineering',
            'Bachelor of Science in Software Engineering',
            'Bachelor of Science in Business Administration',
            'Bachelor of Science in Accountancy',
            'Bachelor of Science in Education',
            'Bachelor of Arts in Psychology',
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Engineering',
            'Bachelor of Science in Mathematics'
        ];
        if (!in_array($course, $allowedCourses)) {
            return response()->json(['success' => false, 'message' => 'Please select a valid course from the list.', 'field' => 'course'], 422);
        }

        if (empty($yearLevel)) {
            return response()->json(['success' => false, 'message' => 'Year level is required.', 'field' => 'year_level'], 422);
        }
        $allowedYears = ['1st', '2nd', '3rd', '4th', '5th'];
        if (!in_array($yearLevel, $allowedYears)) {
            return response()->json(['success' => false, 'message' => 'Please select a valid year level.', 'field' => 'year_level'], 422);
        }

        if (empty($section)) {
            return response()->json(['success' => false, 'message' => 'Section is required.', 'field' => 'section'], 422);
        }
        $allowedSections = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        if (!in_array($section, $allowedSections)) {
            return response()->json(['success' => false, 'message' => 'Please select a valid section.', 'field' => 'section'], 422);
        }

        // Check unique email (globally)
        if (DB::table('users')->where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already registered.', 'field' => 'email'], 409);
        }

        // Check unique student_no within school
        if (DB::table('users')->where('student_no', $studentNo)->where('school_id', $schoolId)->exists()) {
            return response()->json(['success' => false, 'message' => 'This Student No. already exists in this school.', 'field' => 'student_no'], 409);
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => $firstName,
            'middle_name' => !empty($middleName) ? $middleName : null,
            'last_name' => $lastName,
            'suffix' => !empty($suffix) ? $suffix : null,
            'email' => $email,
            'password' => Hash::make($password),
            'student_no' => $studentNo,
            'course' => $course,
            'year_level' => $yearLevel,
            'section' => $section,
            'status' => $status,
            'notes' => trim($request->input('notes', '') ?: null),
            'role' => 'student',
            'school_id' => $schoolId,
            'created_at' => now(),
        ]);

        // Log student creation
        AuditLog::logAction(
            $user,
            'USER_CREATE',
            'user',
            $userId,
            "Created student: {$firstName} {$lastName} ({$email}, Student No: {$studentNo})"
        );

        return response()->json(['success' => true, 'id' => $userId]);
    }

    public function createLibrarian(Request $request)
    {
        $user = $this->requireRole(['admin']);

        // Normalize inputs
        $firstName = trim($request->input('first_name', ''));
        $middleName = trim($request->input('middle_name', ''));
        $lastName = trim($request->input('last_name', ''));
        $suffix = trim($request->input('suffix', ''));
        $email = strtolower(trim($request->input('email', '')));
        $password = $request->input('password', '');
        $status = strtolower(trim($request->input('status', 'active')));
        $schoolId = (int)$request->input('school_id', 0);

        // 🧍 Personal Info Validation
        if (empty($firstName)) {
            return response()->json(['success' => false, 'message' => 'First name is required.', 'field' => 'first_name'], 422);
        }
        if (strlen($firstName) < 2 || strlen($firstName) > 50) {
            return response()->json(['success' => false, 'message' => 'First name must be 2-50 characters.', 'field' => 'first_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $firstName)) {
            return response()->json(['success' => false, 'message' => 'First name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'first_name'], 422);
        }

        if (!empty($middleName)) {
            if (strlen($middleName) < 2 || strlen($middleName) > 50) {
                return response()->json(['success' => false, 'message' => 'Middle name must be 2-50 characters.', 'field' => 'middle_name'], 422);
            }
            if (!preg_match('/^[a-zA-Z\s\-\']+$/', $middleName)) {
                return response()->json(['success' => false, 'message' => 'Middle name contains invalid characters.', 'field' => 'middle_name'], 422);
            }
        }

        if (empty($lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name is required.', 'field' => 'last_name'], 422);
        }
        if (strlen($lastName) < 2 || strlen($lastName) > 50) {
            return response()->json(['success' => false, 'message' => 'Last name must be 2-50 characters.', 'field' => 'last_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'last_name'], 422);
        }

        $allowedSuffixes = ['', 'N/A', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        if (!empty($suffix) && !in_array($suffix, $allowedSuffixes)) {
            return response()->json(['success' => false, 'message' => 'Invalid suffix. Please select from the allowed values.', 'field' => 'suffix'], 422);
        }

        // 📧 Account Details Validation
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Email is required.', 'field' => 'email'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid email address.', 'field' => 'email'], 422);
        }

        if (!in_array($status, ['active', 'disabled', 'inactive'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status value.', 'field' => 'status'], 422);
        }

        if (empty($password)) {
            return response()->json(['success' => false, 'message' => 'Password is required.', 'field' => 'password'], 422);
        }
        if (strlen($password) < 8) {
            return response()->json(['success' => false, 'message' => 'Password must be at least 8 characters.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 uppercase letter.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[a-z]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 lowercase letter.', 'field' => 'password'], 422);
        }
        if (!preg_match('/[0-9]/', $password)) {
            return response()->json(['success' => false, 'message' => 'Password must contain at least 1 number.', 'field' => 'password'], 422);
        }
        // Password must NOT be identical to email
        if (strtolower($password) === strtolower($email)) {
            return response()->json(['success' => false, 'message' => 'Password cannot be the same as your email.', 'field' => 'password'], 422);
        }

        // 🏫 School Assignment Validation
        if ($schoolId <= 0) {
            return response()->json(['success' => false, 'message' => 'School is required for librarians.', 'field' => 'school_id'], 422);
        }
        // Check if school exists and is active
        $school = DB::table('schools')->where('id', $schoolId)->first();
        if (!$school) {
            return response()->json(['success' => false, 'message' => 'Invalid school selected.', 'field' => 'school_id'], 422);
        }
        // Check if school is active (if status column exists)
        if (isset($school->status) && $school->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Selected school is not active.', 'field' => 'school_id'], 422);
        }

        // Check unique email (globally across all users)
        if (DB::table('users')->where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'This email is already in use.', 'field' => 'email'], 409);
        }

        $userId = DB::table('users')->insertGetId([
            'first_name' => $firstName,
            'middle_name' => !empty($middleName) ? $middleName : null,
            'last_name' => $lastName,
            'suffix' => !empty($suffix) ? $suffix : null,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => $status,
            'notes' => trim($request->input('notes', '') ?: null),
            'role' => 'librarian',
            'school_id' => $schoolId,
            'created_at' => now(),
        ]);

        // Log librarian creation
        AuditLog::logAction(
            $user,
            'USER_CREATE',
            'user',
            $userId,
            "Created librarian: {$firstName} {$lastName} ({$email}, School ID: {$schoolId})"
        );

        return response()->json(['success' => true, 'id' => $userId, 'message' => 'Librarian account created successfully!']);
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireLogin();
        $id = (int)$id;

        // Get the existing user
        $existingUser = DB::table('users')->where('id', $id)->first();
        if (!$existingUser) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        // Check if user is a student (for student-specific validations)
        $isStudent = strtolower($existingUser->role ?? '') === 'student';
        $schoolId = (int)($existingUser->school_id ?? 0);

        // Normalize inputs
        $firstName = trim($request->input('first_name', ''));
        $middleName = trim($request->input('middle_name', ''));
        $lastName = trim($request->input('last_name', ''));
        $suffix = trim($request->input('suffix', ''));
        $email = strtolower(trim($request->input('email', '')));
        $status = strtolower(trim($request->input('status', 'active')));
        $password = $request->input('password', '');
        $studentNo = trim($request->input('student_no', ''));
        $course = trim($request->input('course', ''));
        $yearLevel = trim($request->input('year_level', ''));
        $section = trim($request->input('section', ''));

        // 🧍 Personal Info Validation
        if (empty($firstName)) {
            return response()->json(['success' => false, 'message' => 'First name is required.', 'field' => 'first_name'], 422);
        }
        if (strlen($firstName) < 2 || strlen($firstName) > 50) {
            return response()->json(['success' => false, 'message' => 'First name must be 2-50 characters.', 'field' => 'first_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $firstName)) {
            return response()->json(['success' => false, 'message' => 'First name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'first_name'], 422);
        }

        if (!empty($middleName)) {
            if (strlen($middleName) < 2 || strlen($middleName) > 50) {
                return response()->json(['success' => false, 'message' => 'Middle name must be 2-50 characters.', 'field' => 'middle_name'], 422);
            }
            if (!preg_match('/^[a-zA-Z\s\-\']+$/', $middleName)) {
                return response()->json(['success' => false, 'message' => 'Middle name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'middle_name'], 422);
            }
        }

        if (empty($lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name is required.', 'field' => 'last_name'], 422);
        }
        if (strlen($lastName) < 2 || strlen($lastName) > 50) {
            return response()->json(['success' => false, 'message' => 'Last name must be 2-50 characters.', 'field' => 'last_name'], 422);
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $lastName)) {
            return response()->json(['success' => false, 'message' => 'Last name can only contain letters, spaces, hyphens, or apostrophes.', 'field' => 'last_name'], 422);
        }

        $allowedSuffixes = ['', 'N/A', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        if (!empty($suffix) && !in_array($suffix, $allowedSuffixes)) {
            return response()->json(['success' => false, 'message' => 'Invalid suffix. Please select from the allowed values.', 'field' => 'suffix'], 422);
        }

        // 📧 Account & Status Validation
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Email is required.', 'field' => 'email'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Enter a valid email address.', 'field' => 'email'], 422);
        }

        if (!in_array($status, ['active', 'disabled', 'inactive'])) {
            return response()->json(['success' => false, 'message' => 'Status must be Active or Disabled.', 'field' => 'status'], 422);
        }

        // Password validation: Optional in edit mode, but if provided, must meet requirements
        if (!empty($password)) {
            if (strlen($password) < 8) {
                return response()->json(['success' => false, 'message' => 'Password must be at least 8 characters.', 'field' => 'password'], 422);
            }
            if (!preg_match('/[A-Z]/', $password)) {
                return response()->json(['success' => false, 'message' => 'Password must contain at least 1 uppercase letter.', 'field' => 'password'], 422);
            }
            if (!preg_match('/[a-z]/', $password)) {
                return response()->json(['success' => false, 'message' => 'Password must contain at least 1 lowercase letter.', 'field' => 'password'], 422);
            }
            if (!preg_match('/[0-9]/', $password)) {
                return response()->json(['success' => false, 'message' => 'Password must contain at least 1 number.', 'field' => 'password'], 422);
            }
            // Password must NOT be identical to email or student ID
            if (strtolower($password) === strtolower($email)) {
                return response()->json(['success' => false, 'message' => 'Password cannot be the same as your email.', 'field' => 'password'], 422);
            }
            if ($isStudent && $password === $studentNo) {
                return response()->json(['success' => false, 'message' => 'Password cannot be the same as your student ID.', 'field' => 'password'], 422);
            }
        }

        // 🏫 Academic Info Validation (for students)
        if ($isStudent) {
            if (empty($studentNo)) {
                return response()->json(['success' => false, 'message' => 'School ID / Student No. is required.', 'field' => 'student_no'], 422);
            }
            if (!preg_match('/^\d{8}$/', $studentNo)) {
                return response()->json(['success' => false, 'message' => 'Student number must be exactly 8 digits.', 'field' => 'student_no'], 422);
            }

            if (empty($course)) {
                return response()->json(['success' => false, 'message' => 'Course is required.', 'field' => 'course'], 422);
            }
            $allowedCourses = [
                'Bachelor of Science in Computer Science',
                'Bachelor of Science in Information Technology',
                'Bachelor of Science in Information Systems',
                'Bachelor of Science in Computer Engineering',
                'Bachelor of Science in Software Engineering',
                'Bachelor of Science in Business Administration',
                'Bachelor of Science in Accountancy',
                'Bachelor of Science in Education',
                'Bachelor of Arts in Psychology',
                'Bachelor of Science in Nursing',
                'Bachelor of Science in Engineering',
                'Bachelor of Science in Mathematics'
            ];
            if (!in_array($course, $allowedCourses)) {
                return response()->json(['success' => false, 'message' => 'Please select a valid course from the list.', 'field' => 'course'], 422);
            }

            if (empty($yearLevel)) {
                return response()->json(['success' => false, 'message' => 'Year level is required.', 'field' => 'year_level'], 422);
            }
            $allowedYears = ['1st', '2nd', '3rd', '4th', '5th'];
            if (!in_array($yearLevel, $allowedYears)) {
                return response()->json(['success' => false, 'message' => 'Please select a valid year level.', 'field' => 'year_level'], 422);
            }

            if (empty($section)) {
                return response()->json(['success' => false, 'message' => 'Section is required.', 'field' => 'section'], 422);
            }
            $allowedSections = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            if (!in_array($section, $allowedSections)) {
                return response()->json(['success' => false, 'message' => 'Please select a valid section.', 'field' => 'section'], 422);
            }
        }

        // Check unique email (globally, excluding current user)
        $emailExists = DB::table('users')
            ->where('email', $email)
            ->where('id', '!=', $id)
            ->exists();
        if ($emailExists) {
            return response()->json(['success' => false, 'message' => 'This email is already registered.', 'field' => 'email'], 409);
        }

        // Check unique student_no within school (excluding current student)
        if ($isStudent && !empty($studentNo)) {
            $studentNoExists = DB::table('users')
                ->where('student_no', $studentNo)
                ->where('school_id', $schoolId)
                ->where('id', '!=', $id)
                ->exists();
            if ($studentNoExists) {
                return response()->json(['success' => false, 'message' => 'This Student No. already exists in this school.', 'field' => 'student_no'], 409);
            }
        }

        // Build update data
        $updateData = [
            'first_name' => $firstName,
            'middle_name' => !empty($middleName) ? $middleName : null,
            'last_name' => $lastName,
            'suffix' => !empty($suffix) ? $suffix : null,
            'email' => $email,
            'status' => $status,
        ];

        if ($isStudent) {
            $updateData['student_no'] = $studentNo;
            $updateData['course'] = $course;
            $updateData['year_level'] = $yearLevel;
            $updateData['section'] = $section;
        }

        if ($request->has('notes')) {
            $updateData['notes'] = trim($request->input('notes', '') ?: null);
        }

        if ($request->has('school_id') && $user['role'] === 'admin') {
            $updateData['school_id'] = $request->input('school_id');
        }

        // Only update password if provided
        if (!empty($password)) {
            $updateData['password'] = Hash::make($password);
        }

        $oldStatus = $existingUser->status;
        $updated = DB::table('users')->where('id', $id)->update($updateData);
        
        if ($updated === false) {
            return response()->json(['success' => false, 'message' => 'Failed to update user'], 500);
        }

        // Verify the update was successful by fetching the updated record
        $updatedUser = DB::table('users')->where('id', $id)->first();
        if (!$updatedUser) {
            return response()->json(['success' => false, 'message' => 'User not found after update'], 500);
        }

        // Log user update
        $logDetails = [];
        if (isset($updateData['status']) && $updateData['status'] !== $oldStatus) {
            if ($updateData['status'] === 'disabled' || $updateData['status'] === 'inactive') {
                AuditLog::logAction(
                    $user,
                    'USER_DEACTIVATE',
                    'user',
                    $id,
                    "Deactivated user: {$updatedUser->email} (Status: {$oldStatus} → {$updateData['status']})"
                );
            } elseif (($oldStatus === 'disabled' || $oldStatus === 'inactive') && $updateData['status'] === 'active') {
                AuditLog::logAction(
                    $user,
                    'USER_REACTIVATE',
                    'user',
                    $id,
                    "Reactivated user: {$updatedUser->email}"
                );
            }
        }
        if (!empty($password)) {
            AuditLog::logAction(
                $user,
                'USER_RESET_PASSWORD',
                'user',
                $id,
                "Reset password for user: {$updatedUser->email}"
            );
        }
        if (!isset($updateData['status']) || $updateData['status'] === $oldStatus) {
            // Regular update (not status change)
            $updatedFields = array_keys($updateData);
            AuditLog::logAction(
                $user,
                'USER_UPDATE',
                'user',
                $id,
                "Updated user ID {$id}: " . implode(', ', $updatedFields)
            );
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
            $booksQuery = DB::table('books')
                ->where(function($qry) use ($q) {
                    $qry->where('title', 'LIKE', "%{$q}%")
                        ->orWhere('author', 'LIKE', "%{$q}%")
                        ->orWhere('isbn', 'LIKE', "%{$q}%")
                        ->orWhere('category', 'LIKE', "%{$q}%");
                });

            // Librarian-level filtering - librarians only see books they added
            $currentRole = strtolower($user['role'] ?? '');
            if ($currentRole === 'librarian') {
                $userId = (int)($user['id'] ?? 0);
                if ($userId > 0) {
                    // Check if books table has added_by column
                    $hasAddedBy = DB::select("SHOW COLUMNS FROM books LIKE 'added_by'");
                    if (count($hasAddedBy) > 0) {
                        $booksQuery->where('books.added_by', $userId);
                    }
                } else {
                    // Librarian without ID - return empty books
                    $booksQuery->whereRaw('1 = 0'); // Never match anything
                }
            }
            // Admin sees all books (no filter)

            $books = $booksQuery->select('id', 'title', 'author', 'isbn', 'category')
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




