<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
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
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Not authenticated', 'message' => 'Not authenticated'], 401);
            }
            return redirect('/login');
        }

        // Check role if specified
        if (!empty($roles)) {
            $userRole = strtolower($_SESSION['role'] ?? '');
            $allowedRoles = array_map('strtolower', $roles);
            if (!in_array($userRole, $allowedRoles, true)) {
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
                }
                abort(403);
            }
        }

        return $next($request);
    }
}
