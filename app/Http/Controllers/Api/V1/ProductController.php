<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/v1/products
    public function index(Request $request)
    {
        $query = Product::with(['images', 'category', 'vendor'])
            ->where('status', 'active');

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('governorate')) {
            $query->where('governorate', $request->governorate);
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->boolean('has_discount')) {
            $query->where('discount_percentage', '>', 0);
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(20);

        return response()->json($products);
    }

    // GET /api/v1/products/{id}
    public function show($id)
    {
        $product = Product::with(['images', 'category', 'vendor'])
            ->findOrFail($id);

        $product->increment('views_count');

        return response()->json([
            'data' => $product,
        ]);
    }

    // POST /api/v1/products
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendor_profiles,id',
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'condition' => 'required|in:new,used,like_new',
            'price' => 'required|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'governorate' => 'required|string|max:100',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'data' => $product,
        ], 201);
    }

    // PUT /api/v1/products/{id}
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $vendor = $request->user()->vendorProfile;

        if (! $vendor || $product->vendor_id !== $vendor->id) {
            return response()->json([
                'message' => 'مش مسموحلك تعدل المنتج ده',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'condition' => 'sometimes|in:new,used,like_new',
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:active,sold,paused,rejected',
        ]);

        $product->update($validated);

        return response()->json([
            'data' => $product,
        ]);
    }

    // DELETE /api/v1/products/{id}
    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $vendor = $request->user()->vendorProfile;

        if (! $vendor || $product->vendor_id !== $vendor->id) {
            return response()->json([
                'message' => 'مش مسموحلك تحذف المنتج ده',
            ], 403);
        }

        $product->delete();

        return response()->json([
            'message' => 'تم حذف المنتج بنجاح',
        ]);
    }
}