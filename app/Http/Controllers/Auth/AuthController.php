<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole();
        }
        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $credentials = [
            $loginField => $request->email,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            // تسجيل دور المستخدم في الجلسة لمنع تعارض الجلسات بين النوافذ
            $request->session()->put('auth_role', Auth::user()->role);
            return $this->redirectBasedOnRole();
        }

        return back()->withErrors([
            'email' => 'بيانات الدخول غير صحيحة',
        ])->withInput($request->only('email', 'remember'));
    }

    /**
     * Show registration form
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole();
        }
        $subscriptions = \App\Models\Subscription::active()->get();
        return view('auth.register', compact('subscriptions'));
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'facebook_link' => 'nullable|url|max:255',
            'instagram_link' => 'nullable|url|max:255',
            'store_address' => 'nullable|string|max:500',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'terms' => 'required|accepted',
        ], [
            'name.required' => 'اسم التاجر مطلوب',
            'email.required' => 'البريد الالكتروني مطلوب',
            'email.email' => 'البريد الالكتروني غير صحيح',
            'email.unique' => 'البريد الالكتروني مستخدم مسبقاً',
            'phone.required' => 'رقم الهاتف مطلوب',
            'password.required' => 'الرقم السري مطلوب',
            'password.min' => 'الرقم السري يجب ان يكون 6 احرف على الاقل',
            'password.confirmed' => 'تأكيد الرقم السري غير متطابق',
            'terms.required' => 'يجب الموافقة على الشروط والاحكام',
            'terms.accepted' => 'يجب الموافقة على الشروط والاحكام',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp ?? $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'pending',
            'facebook_link' => $request->facebook_link,
            'instagram_link' => $request->instagram_link,
            'store_address' => $request->store_address,
            'subscription_id' => $request->subscription_id,
        ]);

        Auth::login($user);
        request()->session()->put('auth_role', $user->role);

        return redirect()->route('customer.pending');
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /**
     * Redirect user based on their role
     */
    protected function redirectBasedOnRole()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isPending()) {
            return redirect()->route('customer.pending');
        }

        if (!$user->isApproved()) {
            return redirect()->route('customer.pending');
        }

        return redirect()->route('customer.dashboard');
    }
}
