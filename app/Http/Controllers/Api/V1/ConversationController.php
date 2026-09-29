<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // GET /api/v1/conversations
    public function index(Request $request)
    {
        $user = $request->user();
        $vendorId = $user->vendorProfile?->id;

        $conversations = Conversation::where('buyer_id', $user->id)
            ->when($vendorId, function ($q) use ($vendorId) {
                $q->orWhere('vendor_id', $vendorId);
            })
            ->with(['product', 'buyer', 'vendor'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json(['data' => $conversations]);
    }

    // POST /api/v1/conversations
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $buyer = $request->user();

        // don't let a vendor start a conversation with themselves
        if ($buyer->vendorProfile && $buyer->vendorProfile->id === $product->vendor_id) {
            return response()->json(['message' => 'ده منتجك انت'], 422);
        }

        $conversation = Conversation::firstOrCreate([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'vendor_id' => $product->vendor_id,
        ]);

        return response()->json(['data' => $conversation->load(['product', 'vendor'])], 201);
    }

    // GET /api/v1/conversations/{id}/messages
    public function messages(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeAccess($request, $conversation);

        $messages = $conversation->messages()->with('sender')->orderBy('created_at')->get();

        return response()->json(['data' => $messages]);
    }

    // POST /api/v1/conversations/{id}/messages
    public function sendMessage(Request $request, $id)
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeAccess($request, $conversation);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        // حماية العمولة: ممنوع تبادل أرقام التليفونات في المحادثة عشان البيع ميتمش بره الموقع
        if ($this->containsPhoneNumber($validated['body'])) {
            $action = $request->user()->registerPhoneViolation();

            if ($action === 'blocked' || $action === 'blocked_and_frozen') {
                $freshUser = $request->user()->fresh();

                return response()->json([
                    'message' => $freshUser->isFrozen()
                        ? $freshUser->frozenMessage()
                        : 'ممنوع تبادل أرقام التليفونات أو وسائل التواصل في المحادثة، من فضلك أكمل التواصل والاتفاق من خلال الموقع فقط.',
                    'blocked_reason' => 'phone_number_detected',
                ], 422);
            }

            // 'warned': المحاولة الأولى أو التانية - الرسالة بتتبعت عادي، بس مع تحذير
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);

            $conversation->update(['last_message_at' => now()]);

            return response()->json([
                'data' => $message->load('sender'),
                'warning' => 'للحفاظ على حقوقك وحقوق الطرف التاني، يفضّل إتمام التواصل والاتفاق من خلال الموقع فقط. تكرار مشاركة أرقام التواصل هيؤدي لتجميد الحساب.',
            ], 201);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json(['data' => $message->load('sender')], 201);
    }

    /**
     * فحص تقريبي لوجود رقم تليفون (أو أي رقم طويل شكله رقم تواصل) جوه النص.
     * بيدعم الأرقام العربية (٠-٩) وبيتجاهل مسافات/شرطات/نقط بين الأرقام
     * (عشان "010 123 4567" تتلقط برضه). ملحوظة: ده فحص تقريبي وممكن يتلقط
     * بالغلط رقم طويل مش تليفون (زي سعر مكتوب بالأرقام)، وده مقصود عشان
     * الأولوية لحماية العمولة، مش للدقة المطلقة.
     */
    private function containsPhoneNumber(string $text): bool
    {
        $normalized = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $collapsed = preg_replace('/(?<=\d)[\s\-\.]+(?=\d)/u', '', $normalized);

        return (bool) preg_match('/\d{8,}/', $collapsed);
    }

    private function authorizeAccess(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        $vendorId = $user->vendorProfile?->id;

        $isBuyer = $conversation->buyer_id === $user->id;
        $isVendor = $vendorId && $conversation->vendor_id === $vendorId;

        abort_unless($isBuyer || $isVendor, 403, 'مش مسموحلك تشوف المحادثة دي');
    }
}
