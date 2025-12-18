<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolController extends BaseController
{
    /**
     * List all schools (for dropdowns) - displays all schools regardless of status
     */
    public function list()
    {
        try {
            $user = $this->requireLogin();

            // Check if schools table exists
            $tableExists = DB::select("SHOW TABLES LIKE 'schools'");
            if (empty($tableExists)) {
                // Table doesn't exist - return empty list with helpful message
                return response()->json([
                    'success' => true, 
                    'schools' => [],
                    'message' => 'Schools table not found. Please run the migration.'
                ]);
            }

            // Get ALL schools (not filtered by status) for the dropdown
            $schools = DB::table('schools')
                ->select('id', 'name')
                ->orderBy('name', 'ASC')
                ->get()
                ->map(function($school) {
                    return [
                        'id' => (int)$school->id,
                        'name' => $school->name
                    ];
                });

            return response()->json(['success' => true, 'schools' => $schools]);
        } catch (\Exception $e) {
            // Handle any database errors gracefully
            error_log('SchoolController@list error: ' . $e->getMessage());
            return response()->json([
                'success' => true, 
                'schools' => [],
                'error' => 'Unable to load schools. Please contact administrator.'
            ]);
        }
    }

    /**
     * Admin: list all schools (active + inactive)
     */
    public function adminList()
    {
        $this->requireRole(['admin']);
        $schools = DB::table('schools')
            ->select('id','name','status','created_at','updated_at')
            ->orderBy('name')
            ->get();

        return response()->json(['ok' => true, 'schools' => $schools]);
    }

    /**
     * Admin: create new school
     */
    public function create(Request $request)
    {
        $this->requireRole(['admin']);

        $name = trim($request->input('name',''));
        if ($name === '') {
            return response()->json(['ok'=>false,'error'=>'School name is required'],422);
        }

        $exists = DB::table('schools')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        if ($exists) {
            return response()->json(['ok'=>false,'error'=>'School name already exists'],422);
        }

        $id = DB::table('schools')->insertGetId([
            'name' => $name,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['ok'=>true,'id'=>$id,'name'=>$name,'status'=>'active']);
    }

    /**
     * Admin: update school name
     */
    public function update(Request $request, $id)
    {
        $this->requireRole(['admin']);
        $id = (int)$id;
        $name = trim($request->input('name',''));
        if ($name === '') {
            return response()->json(['ok'=>false,'error'=>'School name is required'],422);
        }

        $exists = DB::table('schools')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id','<>',$id)
            ->exists();
        if ($exists) {
            return response()->json(['ok'=>false,'error'=>'School name already exists'],422);
        }

        $affected = DB::table('schools')->where('id',$id)->update([
            'name' => $name,
            'updated_at' => now()
        ]);

        if ($affected === 0) {
            return response()->json(['ok'=>false,'error'=>'School not found'],404);
        }

        return response()->json(['ok'=>true,'id'=>$id,'name'=>$name]);
    }

    /**
     * Admin: set status active/inactive
     */
    public function setStatus(Request $request, $id)
    {
        $this->requireRole(['admin']);
        $id = (int)$id;
        $status = $request->input('status','');
        if (!in_array($status,['active','inactive'])) {
            return response()->json(['ok'=>false,'error'=>'Invalid status'],422);
        }

        $affected = DB::table('schools')->where('id',$id)->update([
            'status' => $status,
            'updated_at' => now()
        ]);

        if ($affected === 0) {
            return response()->json(['ok'=>false,'error'=>'School not found'],404);
        }

        return response()->json(['ok'=>true,'id'=>$id,'status'=>$status]);
    }
}




