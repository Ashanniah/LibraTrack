<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends BaseController
{
    public function list(Request $request)
    {
        $search = $request->input('search', '');
        
        $query = DB::table('categories')
            ->select(
                'categories.id',
                'categories.name',
                'categories.description',
                'categories.created_at',
                DB::raw('COUNT(books.id) as book_count')
            )
            ->leftJoin('books', 'books.category', '=', 'categories.name')
            ->groupBy('categories.id', 'categories.name', 'categories.description', 'categories.created_at');
        
        if ($search) {
            $query->where('categories.name', 'LIKE', '%' . $search . '%');
        }
        
        $categories = $query->orderBy('categories.name', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    public function create(Request $request)
    {
        $user = $this->requireRole(['admin']); // Only admin can create categories
        
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'description' => 'nullable|string|max:1000',
        ]);
        
        $name = trim($request->input('name'));
        $description = trim($request->input('description', ''));
        
        // Check for duplicate category name (case-insensitive)
        $existing = DB::table('categories')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        
        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => 'A category with this name already exists'
            ], 409);
        }
        
        // Insert new category
        $id = DB::table('categories')->insertGetId([
            'name' => $name,
            'description' => $description ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Category created successfully'
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireRole(['admin']); // Only admin can update categories
        
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'description' => 'nullable|string|max:1000',
        ]);
        
        $category = DB::table('categories')->where('id', $id)->first();
        
        if (!$category) {
            return response()->json([
                'success' => false,
                'error' => 'Category not found'
            ], 404);
        }
        
        $newName = trim($request->input('name'));
        $description = trim($request->input('description', ''));
        $oldName = $category->name;
        
        // Check for duplicate category name (case-insensitive, excluding current)
        if (strtolower($newName) !== strtolower($oldName)) {
            $existing = DB::table('categories')
                ->where('id', '!=', $id)
                ->whereRaw('LOWER(name) = ?', [strtolower($newName)])
                ->first();
            
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'error' => 'A category with this name already exists'
                ], 409);
            }
        }
        
        // Update category in categories table
        DB::table('categories')
            ->where('id', $id)
            ->update([
                'name' => $newName,
                'description' => $description ?: null,
                'updated_at' => now(),
            ]);
        
        // Update all books that reference this category by name
        if ($newName !== $oldName) {
            DB::table('books')
                ->where('category', $oldName)
                ->update(['category' => $newName]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
    }

    public function delete(Request $request)
    {
        $user = $this->requireRole(['admin']); // Only admin can delete categories
        
        $id = $request->input('id');
        
        if (!$id) {
            return response()->json([
                'success' => false,
                'error' => 'Category ID is required'
            ], 422);
        }
        
        $category = DB::table('categories')->where('id', $id)->first();
        
        if (!$category) {
            return response()->json([
                'success' => false,
                'error' => 'Category not found'
            ], 404);
        }
        
        // Check if any books are using this category
        $bookCount = DB::table('books')
            ->where('category', $category->name)
            ->count();
        
        if ($bookCount > 0) {
            return response()->json([
                'success' => false,
                'error' => "Cannot delete category: {$bookCount} book(s) are currently using this category"
            ], 400);
        }
        
        // Safe to delete
        DB::table('categories')->where('id', $id)->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
