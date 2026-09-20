<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/v1/auth/register
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'governorate' => 'nullable|string|max:100',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'governorate' => $validated['governorate'] ?? null,
            'role' => 'buyer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // POST /api/v1/auth/login
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['البيانات المدخلة غير صحيحة.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب موقوف حاليًا.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    // GET /api/v1/auth/me
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('vendorProfile'),
        ]);
    }

    // POST /api/v1/auth/forgot-password
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // don't reveal whether the email exists - respond the same either way
        if (! $user) {
            return response()->json([
                'message' => 'لو البريد ده مسجل عندنا، هيوصلك رمز إعادة التعيين',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        EmailOtp::create([
            'email' => $validated['email'],
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        // TODO: replace with real email sending before launch.
        Log::info("Password reset code for {$validated['email']}: {$code}");

        return response()->json([
            'message' => 'لو البريد ده مسجل عندنا، هيوصلك رمز إعادة التعيين',
        ]);
    }

    // POST /api/v1/auth/reset-password
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
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

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'الحساب غير موجود',
            ], 404);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $otp->update(['verified' => true]);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }
}