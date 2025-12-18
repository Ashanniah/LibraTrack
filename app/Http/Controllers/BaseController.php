<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

abstract class BaseController extends Controller
{
    /**
     * Get current user from session
     */
    protected function currentUser()
    {
        // Use PHP native session to match backend PHP authentication
        if (session_status() === PHP_SESSION_NONE) {
            // Use same cookie params as backend/db.php
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
            return null;
        }

        $uid = (int)$_SESSION['uid'];
        $user = DB::table('users')
            ->leftJoin('schools', 'users.school_id', '=', 'schools.id')
            ->where('users.id', $uid)
            ->where('users.status', 'active')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.role', 'users.status', 'users.school_id', 'schools.name as school_name')
            ->first();

        if (!$user) {
            return null;
        }

        return (array) $user;
    }

    /**
     * Require user to be logged in
     */
    protected function requireLogin()
    {
        $user = $this->currentUser();
        if (!$user) {
            abort(401, 'Not authenticated');
        }
        return $user;
    }

    /**
     * Require user to have specific role(s)
     */
    protected function requireRole($roles)
    {
        $user = $this->requireLogin();
        $userRole = strtolower($user['role'] ?? '');
        $allowedRoles = array_map('strtolower', is_array($roles) ? $roles : [$roles]);
        
        if (!in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Forbidden');
        }
        
        return $user;
    }

    /**
     * Check if loans table has school_id column
     */
    protected function loansHasSchoolId()
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM loans LIKE 'school_id'");
            return count($columns) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if books table has school_id column
     */
    protected function booksHasSchoolId()
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM books LIKE 'school_id'");
            return count($columns) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if books table has added_by column
     */
    protected function booksHasAddedBy()
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM books LIKE 'added_by'");
            return count($columns) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}


