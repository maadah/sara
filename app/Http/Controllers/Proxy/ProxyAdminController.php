<?php

namespace App\Http\Controllers\Proxy;

use App\Http\Controllers\Controller;
use App\Models\ProxyApiLog;
use App\Models\ProxyPlatform;
use App\Models\ProxySocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProxyAdminController extends Controller
{
    /**
     * Show password login form.
     */
    public function loginForm()
    {
        if (session('proxy_admin_auth')) {
            return redirect()->route('proxy.admin.dashboard');
        }

        return view('proxy.admin.login');
    }

    /**
     * Verify password.
     */
    public function login(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        if ($request->password === 'sara') {
            session(['proxy_admin_auth' => true]);
            return redirect()->route('proxy.admin.dashboard');
        }

        return back()->with('error', 'كلمة المرور غير صحيحة');
    }

    /**
     * Logout.
     */
    public function logout()
    {
        session()->forget('proxy_admin_auth');
        return redirect()->route('proxy.admin.login');
    }

    /**
     * Dashboard — list all platforms.
     */
    public function dashboard()
    {
        $platforms = ProxyPlatform::withCount('socialAccounts')
            ->orderByDesc('created_at')
            ->get();

        $totalAccounts = ProxySocialAccount::count();
        $recentLogs    = ProxyApiLog::with('platform')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('proxy.admin.dashboard', compact('platforms', 'totalAccounts', 'recentLogs'));
    }

    /**
     * Create platform form.
     */
    public function create()
    {
        return view('proxy.admin.create');
    }

    /**
     * Store new platform.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:100',
            'domain'             => 'required|string|max:255',
            'webhook_url'        => 'nullable|url|max:500',
            'oauth_callback_url' => 'nullable|url|max:500',
        ]);

        $platform = ProxyPlatform::create([
            'name'               => $request->name,
            'domain'             => $request->domain,
            'api_key'            => Str::random(48),
            'api_secret'         => Str::random(64),
            'webhook_url'        => $request->webhook_url ?? '',
            'oauth_callback_url' => $request->oauth_callback_url ?? '',
            'is_active'          => true,
        ]);

        return redirect()->route('proxy.admin.show', $platform)
            ->with('success', 'تم إنشاء المنصة بنجاح. احتفظ بالمفاتيح!');
    }

    /**
     * Show platform details + accounts.
     */
    public function show(ProxyPlatform $platform)
    {
        $platform->load('socialAccounts');
        $logs = $platform->apiLogs()->orderByDesc('created_at')->limit(50)->get();

        return view('proxy.admin.show', compact('platform', 'logs'));
    }

    /**
     * Edit platform.
     */
    public function edit(ProxyPlatform $platform)
    {
        return view('proxy.admin.edit', compact('platform'));
    }

    /**
     * Update platform.
     */
    public function update(Request $request, ProxyPlatform $platform)
    {
        $request->validate([
            'name'               => 'required|string|max:100',
            'domain'             => 'required|string|max:255',
            'webhook_url'        => 'nullable|url|max:500',
            'oauth_callback_url' => 'nullable|url|max:500',
            'is_active'          => 'boolean',
        ]);

        $platform->update($request->only('name', 'domain', 'webhook_url', 'oauth_callback_url', 'is_active'));

        return redirect()->route('proxy.admin.show', $platform)
            ->with('success', 'تم تحديث المنصة');
    }

    /**
     * Regenerate API keys.
     */
    public function regenerateKeys(ProxyPlatform $platform)
    {
        $platform->update([
            'api_key'    => Str::random(48),
            'api_secret' => Str::random(64),
        ]);

        return redirect()->route('proxy.admin.show', $platform)
            ->with('success', 'تم تجديد المفاتيح. تأكد من تحديثها في المنصة الأخرى!');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(ProxyPlatform $platform)
    {
        $platform->update(['is_active' => !$platform->is_active]);

        $status = $platform->is_active ? 'تفعيل' : 'تعطيل';
        return back()->with('success', "تم {$status} المنصة");
    }

    /**
     * Documentation page.
     */
    public function docs()
    {
        return view('proxy.admin.docs');
    }
}
