<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommissionTransaction;
use Illuminate\Http\Request;

class SaleConfirmationController extends Controller
{
    // GET /api/v1/buyer/sale-confirmations
    // البيعات اللي لسه مستنية تأكيد المشتري الحالي (دخل عليه الميعاد لسه، ولا اتحسم)
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $confirmations = CommissionTransaction::with(['vendor', 'conversation.product'])
            ->whereHas('conversation', fn ($q) => $q->where('buyer_id', $userId))
            ->where('buyer_confirmation', 'pending')
            ->where('confirmation_deadline', '>=', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $confirmations]);
    }

    // POST /api/v1/buyer/sale-confirmations/{id}/respond
    // body: { confirm: true|false }
    public function respond(Request $request, $id)
    {
        $tx = CommissionTransaction::with('conversation')->findOrFail($id);

        abort_unless(
            $tx->conversation && $tx->conversation->buyer_id === $request->user()->id,
            403,
            'مش مسموحلك ترد على البيع ده'
        );

        if ($tx->buyer_confirmation !== 'pending') {
            return response()->json(['message' => 'تم الرد على البيع ده قبل كده'], 422);
        }

        if ($tx->confirmation_deadline && now()->greaterThan($tx->confirmation_deadline)) {
            return response()->json(['message' => 'انتهت مهلة الرد على البيع ده، هتتراجع من إدارة الموقع'], 422);
        }

        $validated = $request->validate([
            'confirm' => 'required|boolean',
        ]);

        if ($validated['confirm']) {
            $tx->update([
                'buyer_confirmation' => 'confirmed',
                'buyer_response_at' => now(),
            ]);

            $tx->vendor->increment('pending_commission_balance', $tx->amount);
        } else {
            $tx->update([
                'buyer_confirmation' => 'rejected',
                'buyer_response_at' => now(),
            ]);
        }

        return response()->json(['data' => $tx->fresh()]);
    }
}
