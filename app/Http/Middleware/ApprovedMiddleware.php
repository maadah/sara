<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedMiddleware
{
    /**
     * Handle an incoming request - ensures customer is approved and subscription is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin bypass
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Customer must be approved
        if (!$user->isApproved()) {
            return redirect()->route('customer.pending');
        }

        // Check if subscription has expired
        if ($user->subscription_expires_at && $user->subscription_expires_at->isPast()) {
            return redirect()->route('customer.expired');
        }

        return $next($request);
    }
}
