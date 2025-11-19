<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends BaseController
{
    public function list()
    {
        $user = $this->requireRole(['student']);

        $favorites = DB::table('favorites as f')
            ->join('books as b', 'b.id', '=', 'f.book_id')
            ->where('f.user_id', $user['id'])
            ->select('b.*', 'f.id as favorite_id', 'f.created_at as favorited_at')
            ->orderBy('f.created_at', 'desc')
            ->get()
            ->map(function($item) {
                $item->cover_url = $item->cover ? ("/uploads/covers/".$item->cover) : null;
                $item->is_favorite = true;
                return $item;
            });

        return response()->json(['ok' => true, 'items' => $favorites]);
    }

    public function toggle(Request $request)
    {
        $user = $this->requireRole(['student']);

        $request->validate([
            'book_id' => 'required|integer|min:1',
        ]);

        $bookId = (int)$request->input('book_id');

        // Check if already favorited
        $exists = DB::table('favorites')
            ->where('user_id', $user['id'])
            ->where('book_id', $bookId)
            ->exists();

        if ($exists) {
            // Remove from favorites
            DB::table('favorites')
                ->where('user_id', $user['id'])
                ->where('book_id', $bookId)
                ->delete();

            return response()->json(['ok' => true, 'favorited' => false]);
        } else {
            // Add to favorites
            DB::table('favorites')->insert([
                'user_id' => $user['id'],
                'book_id' => $bookId,
                'created_at' => now(),
            ]);

            return response()->json(['ok' => true, 'favorited' => true]);
        }
    }
}





