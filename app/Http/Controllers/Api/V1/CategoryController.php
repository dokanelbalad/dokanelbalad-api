<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/v1/categories
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    // GET /api/v1/categories/{slug}
    public function show($slug)
    {
        $category = Category::where('slug', $slug)
            ->with('children')
            ->firstOrFail();

        return response()->json([
            'data' => $category,
        ]);
    }

    // POST /api/v1/admin/categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name_ar' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'slug' => 'required|string|max:120|unique:categories,slug',
            'icon' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'data' => $category,
        ], 201);
    }
}