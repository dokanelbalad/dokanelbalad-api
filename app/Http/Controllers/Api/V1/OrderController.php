<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CommissionTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // POST /api/v1/orders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cod,online',
            'shipping_address' => 'required|string',
            'shipping_governorate' => 'required|string|max:100',
        ]);

        $buyer = $request->user();

        // group items by vendor - each vendor gets its own order
        $productsById = Product::with('vendor')
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $itemsByVendor = collect($validated['items'])->groupBy(function ($item) use ($productsById) {
            return $productsById[$item['product_id']]->vendor_id;
        });

        $createdOrders = [];

        DB::transaction(function () use ($itemsByVendor, $productsById, $validated, $buyer, &$createdOrders) {
            foreach ($itemsByVendor as $vendorId => $items) {
                $vendor = $productsById[$items->first()['product_id']]->vendor;

                $subtotal = 0;
                $shippingTotal = 0;
                foreach ($items as $item) {
                    $product = $productsById[$item['product_id']];
                    $subtotal += $product->price_after_discount * $item['quantity'];

                    if ($product->shipping_paid_by === 'buyer') {
                        $shippingTotal += $product->shipping_fee;
                    }
                }

                $commissionRate = $vendor->commission_rate;
                $commissionAmount = round($subtotal * ($commissionRate / 100), 2);

                $buyerCommissionRate = (float) \App\Models\PlatformSetting::get('buyer_commission_rate', 0);
                $buyerCommissionAmount = round($subtotal * ($buyerCommissionRate / 100), 2);

                $total = $subtotal + $buyerCommissionAmount + $shippingTotal;

                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                    'buyer_id' => $buyer->id,
                    'vendor_id' => $vendorId,
                    'status' => 'pending',
                    'payment_method' => $validated['payment_method'],
                    'subtotal' => $subtotal,
                    'commission_rate_applied' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'buyer_commission_rate_applied' => $buyerCommissionRate,
                    'buyer_commission_amount' => $buyerCommissionAmount,
                    'total' => $total,
                    'shipping_address' => $validated['shipping_address'],
                    'shipping_governorate' => $validated['shipping_governorate'],
                ]);

                foreach ($items as $item) {
                    $product = $productsById[$item['product_id']];
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price_after_discount,
                    ]);
                }

                // if paid online, commission is auto-deducted immediately.
                // if COD, it's logged as pending until buyer confirms delivery.
                CommissionTransaction::create([
                    'vendor_id' => $vendorId,
                    'order_id' => $order->id,
                    'amount' => $commissionAmount + $buyerCommissionAmount,
                    'type' => $validated['payment_method'] === 'online' ? 'online_auto_deducted' : 'cod_pending',
                    'status' => 'pending',
                ]);

                $createdOrders[] = $order->load('items.product');
            }
        });

        return response()->json([
            'data' => $createdOrders,
        ], 201);
    }

    // GET /api/v1/orders
    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with(['vendor', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }

    // GET /api/v1/orders/{id}
    public function show(Request $request, $id)
    {
        $order = Order::with(['vendor', 'items.product', 'buyer'])
            ->findOrFail($id);

        $isBuyer = $order->buyer_id === $request->user()->id;
        $isVendor = $request->user()->vendorProfile && $order->vendor_id === $request->user()->vendorProfile->id;

        if (! $isBuyer && ! $isVendor) {
            return response()->json(['message' => 'مش مسموحلك تشوف الطلب ده'], 403);
        }

        return response()->json(['data' => $order]);
    }

    // POST /api/v1/orders/{id}/confirm-delivery
    public function confirmDelivery(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'مش مسموحلك تأكد الطلب ده'], 403);
        }

        $order->update([
            'status' => 'delivered',
            'confirmed_by_buyer_at' => now(),
        ]);

        if ($order->payment_method === 'cod') {
            $commissionTx = CommissionTransaction::where('order_id', $order->id)->first();
            if ($commissionTx) {
                $commissionTx->update(['type' => 'cod_settled']);
            }

            $order->vendor->increment('pending_commission_balance', $order->commission_amount);
        }

        return response()->json(['data' => $order]);
    }
}