<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CommissionTransaction;
use App\Services\PaymobService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PaymobService $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

    // POST /api/v1/orders/{id}/pay
    public function startPayment(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'مش مسموحلك تدفع للطلب ده'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'الطلب ده متدفوع بالفعل'], 422);
        }

        try {
            $iframeUrl = $this->paymob->startPayment($order);

            return response()->json([
                'data' => [
                    'payment_url' => $iframeUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حصل خطأ أثناء بدء عملية الدفع، حاول تاني',
            ], 500);
        }
    }

    // POST /api/v1/payments/webhook
    // Paymob calls this after every transaction attempt (success or fail)
    public function webhook(Request $request)
    {
        $data = $request->all();
        $transaction = $data['obj'] ?? [];

        $receivedHmac = $request->query('hmac', '');

        if (! $this->paymob->verifyHmac($transaction, $receivedHmac)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $merchantOrderId = $transaction['order']['merchant_order_id'] ?? null;
        $success = $transaction['success'] ?? false;
        $transactionId = $transaction['id'] ?? null;

        if (! $merchantOrderId) {
            return response()->json(['message' => 'missing order reference'], 422);
        }

        $order = Order::where('order_number', $merchantOrderId)->first();

        if (! $order) {
            return response()->json(['message' => 'order not found'], 404);
        }

        if ($success) {
            $order->update([
                'payment_status' => 'paid',
                'paymob_transaction_id' => $transactionId,
                'status' => 'confirmed',
            ]);

            // online payment: commission is deducted immediately, no pending balance needed
            CommissionTransaction::where('order_id', $order->id)
                ->update(['type' => 'online_auto_deducted', 'status' => 'settled']);
        } else {
            $order->update([
                'payment_status' => 'failed',
                'paymob_transaction_id' => $transactionId,
            ]);
        }

        return response()->json(['message' => 'ok']);
    }

    // GET /api/v1/orders/{id}/payment-status
    public function paymentStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'مش مسموحلك تشوف الطلب ده'], 403);
        }

        return response()->json([
            'data' => [
                'payment_status' => $order->payment_status,
                'order_status' => $order->status,
            ],
        ]);
    }
}