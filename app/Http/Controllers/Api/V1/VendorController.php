<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    // POST /api/v1/vendor/register
    public function register(Request $request)
    {
        $user = $request->user();

        if ($user->vendorProfile) {
            return response()->json(['message' => 'عندك حساب بائع بالفعل'], 422);
        }

        $validated = $request->validate([
            'store_name' => 'required|string|max:150',
            'store_description' => 'nullable|string',
            'entity_type' => 'required|in:individual,retail_shop,wholesale,company',
            'verification_tier' => 'required|in:basic,verified',
            'national_id_number' => 'required_if:verification_tier,basic|nullable|string|max:20',
            'national_id_front_image' => 'required_if:verification_tier,basic|nullable|image|max:4096',
            'national_id_back_image' => 'required_if:verification_tier,basic|nullable|image|max:4096',
            'commercial_register_no' => 'required_if:verification_tier,verified|nullable|string|max:50',
            'commercial_register_image' => 'required_if:verification_tier,verified|nullable|image|max:4096',
            'is_vat_registered' => 'nullable|boolean',
            'tax_registration_number' => 'required_if:is_vat_registered,true|nullable|string|max:30',
            'address_line1' => 'required|string|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city' => 'required|string|max:100',
        ]);

        $commissionRate = $validated['verification_tier'] === 'verified' ? 5.00 : 10.00;

        $data = [
            'store_name' => $validated['store_name'],
            'store_description' => $validated['store_description'] ?? null,
            'vendor_type' => in_array($validated['entity_type'], ['company', 'wholesale']) ? 'company' : 'individual',
            'entity_type' => $validated['entity_type'],
            'verification_tier' => $validated['verification_tier'],
            'national_id_number' => $validated['national_id_number'] ?? null,
            'commercial_register_no' => $validated['commercial_register_no'] ?? null,
            'is_vat_registered' => $validated['is_vat_registered'] ?? false,
            'tax_registration_number' => $validated['tax_registration_number'] ?? null,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'commission_rate' => $commissionRate,
            'status' => 'pending',
        ];

        if ($request->hasFile('national_id_front_image')) {
            $data['national_id_front_image'] = $request->file('national_id_front_image')->store('vendor-documents', 'public');
        }

        if ($request->hasFile('national_id_back_image')) {
            $data['national_id_back_image'] = $request->file('national_id_back_image')->store('vendor-documents', 'public');
        }

        if ($request->hasFile('commercial_register_image')) {
            $data['commercial_register_image'] = $request->file('commercial_register_image')->store('vendor-documents', 'public');
        }

        $vendor = $user->vendorProfile()->create($data);

        $user->update(['role' => 'seller']);

        return response()->json(['data' => $vendor], 201);
    }

    // GET /api/v1/vendor/dashboard
    public function dashboard(Request $request)
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return response()->json([
                'message' => 'هذا الحساب مش بائع بعد',
            ], 404);
        }

        $productsCount = $vendor->products()->count();
        $ordersCount = $vendor->orders()->count();
        $totalSales = $vendor->orders()->sum('total');

        return response()->json([
            'vendor' => $vendor,
            'stats' => [
                'products_count' => $productsCount,
                'orders_count' => $ordersCount,
                'total_sales' => $totalSales,
                'pending_commission_balance' => $vendor->pending_commission_balance,
            ],
        ]);
    }

    // GET /api/v1/vendor/products
    public function products(Request $request)
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return response()->json([
                'message' => 'هذا الحساب مش بائع بعد',
            ], 404);
        }

        $products = $vendor->products()
            ->with(['images', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($products);
    }

    // POST /api/v1/vendor/products
    public function storeProduct(Request $request)
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return response()->json([
                'message' => 'هذا الحساب مش بائع بعد',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'condition' => 'required|in:new,used,like_new',
            'price' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:90',
            'shipping_fee' => 'nullable|numeric|min:0',
            'shipping_paid_by' => 'nullable|in:vendor,buyer',
            'quantity' => 'nullable|integer|min:1',
            'governorate' => 'required|string|max:100',
        ]);

        $product = $vendor->products()->create($validated);

        return response()->json([
            'data' => $product,
        ], 201);
    }

    // GET /api/v1/vendor/orders
    public function orders(Request $request)
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return response()->json([
                'message' => 'هذا الحساب مش بائع بعد',
            ], 404);
        }

        $orders = $vendor->orders()
            ->with(['buyer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }
}