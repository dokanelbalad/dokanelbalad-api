<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\CommissionTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // GET /api/v1/admin/overview
    public function overview()
    {
        return response()->json([
            'data' => [
                'total_users' => User::count(),
                'total_vendors' => VendorProfile::count(),
                'pending_vendors' => VendorProfile::where('status', 'pending')->count(),
                'total_products' => \App\Models\Product::count(),
                'total_orders' => Order::count(),
                'total_pending_commission' => VendorProfile::sum('pending_commission_balance'),
            ],
        ]);
    }

    // GET /api/v1/admin/vendors?status=pending
    public function vendors(Request $request)
    {
        $query = VendorProfile::with('user')->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json(['data' => $query->get()]);
    }

    // POST /api/v1/admin/vendors/{id}/approve
    public function approveVendor($id)
    {
        $vendor = VendorProfile::findOrFail($id);
        $vendor->update(['status' => 'approved']);

        return response()->json(['data' => $vendor]);
    }

    // POST /api/v1/admin/vendors/{id}/reject
    public function rejectVendor($id)
    {
        $vendor = VendorProfile::findOrFail($id);
        $vendor->update(['status' => 'rejected']);

        return response()->json(['data' => $vendor]);
    }

    // GET /api/v1/admin/categories
    public function categories()
    {
        return response()->json(['data' => Category::orderBy('name_ar')->get()]);
    }

        // POST /api/v1/admin/categories
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'slug' => 'required|string|max:100|unique:categories,slug',
            'icon' => 'nullable|string|max:10',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($validated);

        return response()->json(['data' => $category], 201);
    }

        // PUT /api/v1/admin/categories/{id}
    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:100',
            'name_en' => 'nullable|string|max:100',
            'slug' => 'sometimes|string|max:100|unique:categories,slug,' . $id,
            'icon' => 'nullable|string|max:10',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($validated);

        return response()->json(['data' => $category]);
    }

    // DELETE /api/v1/admin/categories/{id}
    public function destroyCategory($id)
    {
        Category::findOrFail($id)->delete();

        return response()->json(['message' => 'تم حذف التصنيف']);
    }

    // GET /api/v1/admin/commissions/pending
    public function pendingCommissions()
    {
        $vendors = VendorProfile::with('user')
            ->where('pending_commission_balance', '>', 0)
            ->orderBy('pending_commission_balance', 'desc')
            ->get();

        return response()->json(['data' => $vendors]);
    }

    // POST /api/v1/admin/commissions/{vendorId}/collect
    public function collectCommission($vendorId)
    {
        $vendor = VendorProfile::findOrFail($vendorId);

        $amount = $vendor->pending_commission_balance;
        $vendor->update(['pending_commission_balance' => 0]);

        CommissionTransaction::where('vendor_id', $vendorId)
            ->where('type', 'cod_settled')
            ->update(['type' => 'collected']);

        return response()->json([
            'data' => $vendor,
            'collected_amount' => $amount,
        ]);
    }

    // GET /api/v1/admin/products
    public function products()
    {
        $products = \App\Models\Product::with(['category', 'vendor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $products]);
    }

    // PUT /api/v1/admin/products/{id}/category
    public function updateProductCategory(Request $request, $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = \App\Models\Product::findOrFail($id);
        $product->update(['category_id' => $validated['category_id']]);

        return response()->json(['data' => $product->load('category')]);
    }

    // PUT /api/v1/admin/products/{id}/discount
    public function updateProductDiscount(Request $request, $id)
    {
        $validated = $request->validate([
            'discount_percentage' => 'required|numeric|min:0|max:90',
        ]);

        $product = \App\Models\Product::findOrFail($id);
        $product->update(['discount_percentage' => $validated['discount_percentage']]);

        return response()->json(['data' => $product]);
    }
}