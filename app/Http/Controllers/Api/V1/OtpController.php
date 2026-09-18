<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    // POST /api/v1/otp/send
    public function send(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $code = (string) random_int(100000, 999999);

        EmailOtp::create([
            'email' => $validated['email'],
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        // TODO: replace with real email sending before launch.
        // For now the code is written to the Laravel log file for local testing.
        Log::info("OTP for {$validated['email']}: {$code}");

        return response()->json([
            'message' => 'تم إرسال رمز التحقق',
        ]);
    }

    // POST /api/v1/otp/verify
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $otp = EmailOtp::where('email', $validated['email'])
            ->where('code', $validated['code'])
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json([
                'message' => 'الكود غلط أو منتهي الصلاحية',
            ], 422);
        }

        $otp->update(['verified' => true]);

        return response()->json([
            'message' => 'تم التحقق بنجاح',
            'verified' => true,
        ]);
    }
}