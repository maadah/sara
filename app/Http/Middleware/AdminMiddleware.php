<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return redirect()->route('login')->with('error', 'غير مصرح بالدخول');
        }

        $user = auth()->user();

        // التحقق من تعارض الجلسات: إذا كانت الجلسة مسجلة كـ customer لكن المستخدم الحالي admin
        // هذا يحدث عند تسجيل الدخول بنافذتين مختلفتين في نفس المتصفح
        $sessionRole = $request->session()->get('auth_role');
        if ($sessionRole === 'customer' && $user->isAdmin()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->with('error', 'تعارض في الجلسة. يرجى تسجيل الدخول مرة أخرى.');
        }

        if (!$user->isAdmin()) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return redirect()->route('login')->with('error', 'غير مصرح بالدخول');
        }

        return $next($request);
    }
}
