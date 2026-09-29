<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * أي حساب (بائع أو مشتري) مجمّد بيقدر يسجّل دخول ويشوف بياناته بس (/auth/me)،
     * وأي حاجة تانية بترفض من هنا برسالة واضحة بالسبب.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isFrozen()) {
            return response()->json([
                'message' => $user->frozenMessage(),
                'frozen' => true,
                'frozen_reason' => $user->permanently_banned ? 'permanently_banned' : $user->frozen_reason,
            ], 423);
        }

        return $next($request);
    }
}
