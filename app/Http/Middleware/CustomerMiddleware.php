<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin should not access customer routes
        if ($user->isAdmin()) {
            // التحقق من أن هذه الجلسة كانت مسجلة أصلاً كـ admin
            // (حماية من تعارض الجلسات عند فتح نافذتين مختلفتين)
            $sessionRole = $request->session()->get('auth_role');

            // إذا كانت الجلسة مسجلة كـ customer بالأصل لكن المستخدم الحالي admin
            // فهذا يعني تعارض في الجلسة — نقوم بتسجيل الخروج وإعادة التوجيه لتسجيل الدخول
            if ($sessionRole === 'customer') {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')
                    ->with('error', 'انتهت جلستك. يرجى تسجيل الدخول مرة أخرى.');
            }

            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
