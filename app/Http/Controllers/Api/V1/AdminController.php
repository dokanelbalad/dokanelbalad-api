<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\CommissionTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // أول عدد من البائعين بياخدوا عمولة مخفضة مدى الحياة ("البائعين المؤسسين")
    const FOUNDING_SELLERS_LIMIT = 100;
    const STANDARD_COMMISSION_RATE = 2.00;
    const FOUNDING_COMMISSION_RATE = 1.00;

    // GET /api/v1/admin/overview
    public function overview()
    {
        $frozenAccounts = User::where(function ($q) {
            $q->whereNotNull('frozen_reason')->orWhere('permanently_banned', true);
        })->count();

        $needsReview = CommissionTransaction::whereNull('order_id')
            ->whereIn('buyer_confirmation', ['rejected', 'expired'])
            ->count();

        return response()->json([
            'data' => [
                'total_users' => User::count(),
                'total_vendors' => VendorProfile::count(),
                'pending_vendors' => VendorProfile::where('status', 'pending')->count(),
                'frozen_accounts' => $frozenAccounts,
                'offplatform_sales_needing_review' => $needsReview,
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

        // لو البائع ده هو رقم 100 أو أقل من ضمن المعتمدين، بيبقى "بائع مؤسس" بعمولة 1% مدى الحياة.
        // بنعتبره مؤسس لو كان متعلّم كده أصلاً (مفيش رجوع بعد ما ياخد اللقب)، أو لو عدد
        // المؤسسين لسه ما وصلش الحد الأقصى.
        $foundingCount = VendorProfile::where('is_founding_seller', true)->count();
        $isFounding = $vendor->is_founding_seller || $foundingCount < self::FOUNDING_SELLERS_LIMIT;

        $vendor->update([
            'status' => 'approved',
            'is_founding_seller' => $isFounding,
            'commission_rate' => $isFounding ? self::FOUNDING_COMMISSION_RATE : self::STANDARD_COMMISSION_RATE,
        ]);

        return response()->json(['data' => $vendor]);
    }

    // POST /api/v1/admin/vendors/{id}/reject
    public function rejectVendor($id)
    {
        $vendor = VendorProfile::findOrFail($id);
        $vendor->update(['status' => 'rejected']);

        return response()->json(['data' => $vendor]);
    }

    // POST /api/v1/admin/vendors/{id}/block
    // تجميد يدوي من الإدارة لحساب البائع بالكامل (منفصل عن التجميد التلقائي بسبب تأخر السداد)
    public function blockVendor($id)
    {
        $vendor = VendorProfile::with('user')->findOrFail($id);
        $vendor->user?->freezeAccount('admin_manual');

        return response()->json(['data' => $vendor->load('user')]);
    }

    // POST /api/v1/admin/vendors/{id}/unblock
    public function unblockVendor($id)
    {
        $vendor = VendorProfile::with('user')->findOrFail($id);
        $vendor->user?->unfreezeAccount();

        return response()->json(['data' => $vendor->load('user')]);
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

        $vendor->load('user');
        $amount = $vendor->pending_commission_balance;

        $vendor->update(['pending_commission_balance' => 0]);

        // لو كان الحساب مجمّد بسبب عمولة متأخرة، تحصيل المستحق بالكامل بيرفع التجميد تلقائياً
        // (لو كان مجمّد لسبب تاني زي مشاركة أرقام أو تجميد يدوي، ده بيفضل زي ما هو)
        if ($vendor->user && $vendor->user->frozen_reason === 'commission_overdue') {
            $vendor->user->unfreezeAccount();
        }

        // ملحوظة: العمود type مسموح له قيم محددة بس (cod_settled / cod_pending / online_auto_deducted)
        // ومفيهوش 'collected' - ده كان فيه خطأ قديم هنا بيحاول يحط قيمة مش موجودة في القائمة.
        // اللي فعلاً بيدل على إن العمولة اتحصّلت هو عمود status.
        CommissionTransaction::where('vendor_id', $vendorId)
            ->where('type', 'cod_settled')
            ->where('status', 'pending')
            ->update(['status' => 'collected', 'settled_at' => now()]);

        return response()->json([
            'data' => $vendor,
            'collected_amount' => $amount,
        ]);
    }

    // GET /api/v1/admin/accounts/frozen
    // كل حساب مجمّد أو مغلق نهائياً دلوقتي، بائع كان أو مشتري
    public function frozenAccounts()
    {
        $accounts = User::with('vendorProfile:id,user_id,store_name')
            ->where(function ($q) {
                $q->whereNotNull('frozen_reason')->orWhere('permanently_banned', true);
            })
            ->orderBy('updated_at', 'desc')
            ->get([
                'id', 'name', 'email', 'phone', 'role',
                'frozen_reason', 'frozen_until', 'permanently_banned',
                'phone_violation_strikes', 'phone_freeze_count', 'commission_freeze_count',
                'updated_at',
            ]);

        return response()->json(['data' => $accounts]);
    }

    // POST /api/v1/admin/accounts/{id}/freeze
    // تجميد يدوي لأي حساب (بائع أو مشتري)، لأسباب مش مغطاة بالتجميد التلقائي
    public function freezeUser($id)
    {
        $user = User::findOrFail($id);
        $user->freezeAccount('admin_manual');

        return response()->json(['data' => $user->fresh()]);
    }

    // POST /api/v1/admin/accounts/{id}/unfreeze
    // ده تدخل إداري كامل: بيرجّع الحساب يشتغل حتى لو كان "مغلق نهائياً"، لأن قرار
    // الإلغاء النهائي للحظر من اختصاص الإدارة بس مهما كان سبب التجميد الأصلي
    public function unfreezeUser($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'frozen_reason' => null,
            'frozen_until' => null,
            'permanently_banned' => false,
        ]);

        return response()->json(['data' => $user->fresh()]);
    }

    // GET /api/v1/admin/offplatform-sales/pending-review
    // بيعات بره الموقع اتبلّغ عنها والمشتري رفضها أو فات ميعاد رده (expired) - محتاجة قرار إدارة
    public function offplatformSalesPendingReview()
    {
        $sales = CommissionTransaction::with(['vendor.user', 'conversation.buyer', 'conversation.product'])
            ->whereNull('order_id')
            ->whereIn('buyer_confirmation', ['rejected', 'expired'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $sales]);
    }

    // POST /api/v1/admin/offplatform-sales/{id}/approve
    // الإدارة راجعت الحالة وقررت إن العمولة مستحقة فعلاً رغم رفض/عدم رد المشتري
    public function approveOffplatformSale($id)
    {
        $tx = CommissionTransaction::findOrFail($id);

        if (! in_array($tx->buyer_confirmation, ['rejected', 'expired'])) {
            return response()->json(['message' => 'الحالة دي مش محتاجة مراجعة'], 422);
        }

        $tx->update([
            'buyer_confirmation' => 'admin_approved',
            'buyer_response_at' => now(),
        ]);

        $tx->vendor->increment('pending_commission_balance', $tx->amount);

        return response()->json(['data' => $tx->fresh()]);
    }

    // POST /api/v1/admin/offplatform-sales/{id}/dismiss
    // الإدارة قررت إن مفيش عمولة مستحقة (اقتناع بإن البيع ما حصلش فعلاً أو الشك مش كافي)
    public function dismissOffplatformSale($id)
    {
        $tx = CommissionTransaction::findOrFail($id);

        if (! in_array($tx->buyer_confirmation, ['rejected', 'expired'])) {
            return response()->json(['message' => 'الحالة دي مش محتاجة مراجعة'], 422);
        }

        $tx->update([
            'buyer_confirmation' => 'dismissed',
            'buyer_response_at' => now(),
        ]);

        return response()->json(['data' => $tx->fresh()]);
    }

    // GET /api/v1/admin/offplatform-sales/pending-intervention
    // بيعات لسه "مستنية رد المشتري" (ما فاتش عليها 24 ساعة بعد) - للتدخل الفوري الاستثنائي بس
    public function offplatformSalesPendingIntervention()
    {
        $sales = CommissionTransaction::with(['vendor.user', 'conversation.buyer', 'conversation.product'])
            ->whereNull('order_id')
            ->where('buyer_confirmation', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $sales]);
    }

    // POST /api/v1/admin/offplatform-sales/{id}/force-resolve
    // تدخل إداري فوري على حالة لسه في وقتها (قبل ما تتحول rejected/expired) - لحالات
    // استثنائية بس (بلاغ أو شكوى وصلت)، مش القاعدة العادية. body: { decision: "approve"|"dismiss" }
    public function forceResolveOffplatformSale(Request $request, $id)
    {
        $tx = CommissionTransaction::findOrFail($id);

        if (in_array($tx->buyer_confirmation, ['confirmed', 'admin_approved', 'dismissed'])) {
            return response()->json(['message' => 'الحالة دي اتحسمت بالفعل'], 422);
        }

        $validated = $request->validate([
            'decision' => 'required|in:approve,dismiss',
        ]);

        if ($validated['decision'] === 'approve') {
            $tx->update(['buyer_confirmation' => 'admin_approved', 'buyer_response_at' => now()]);
            $tx->vendor->increment('pending_commission_balance', $tx->amount);
        } else {
            $tx->update(['buyer_confirmation' => 'dismissed', 'buyer_response_at' => now()]);
        }

        return response()->json(['data' => $tx->fresh()]);
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
