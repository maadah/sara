<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProxyAdminPassword
{
    /**
     * Password gate for the proxy admin panel.
     * User must have session('proxy_admin_auth') = true to access.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!session('proxy_admin_auth')) {
            return redirect()->route('proxy.admin.login');
        }

        return $next($request);
    }
}
