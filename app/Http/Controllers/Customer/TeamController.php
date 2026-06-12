<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class TeamController extends Controller
{
    public function index()
    {
        $members = auth()->user()->teamMembers()->latest()->get();

        return view('customer.team.index', compact('members'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Rules\Password::defaults()],
            'team_role' => ['nullable', 'string', 'max:255'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'approved',
            'merchant_id' => auth()->id(),
            'team_role' => $request->team_role,
        ]);

        return redirect()->route('customer.team.index')->with('success', 'تم إضافة عضو الفريق بنجاح');
    }

    public function update(Request $request, User $user)
    {
        if ($user->merchant_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_role' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $request->name,
            'team_role' => $request->team_role,
        ]);

        return back()->with('success', 'تم تحديث بيانات العضو بنجاح');
    }

    public function destroy(User $user)
    {
        if ($user->merchant_id !== auth()->id()) {
            abort(403);
        }

        $user->delete();

        return redirect()->route('customer.team.index')->with('success', 'تم حذف العضو بنجاح');
    }
}
