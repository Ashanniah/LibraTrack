<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolController extends BaseController
{
    public function list()
    {
        $user = $this->requireLogin();

        $schools = DB::table('schools')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function($school) {
                return [
                    'id' => (int)$school->id,
                    'name' => $school->name
                ];
            });

        return response()->json(['success' => true, 'schools' => $schools]);
    }
}




