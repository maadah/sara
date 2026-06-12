<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];

        return view('customer.settings.index', compact('user', 'settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'store_name'                   => 'nullable|string|max:255',
            'store_description'            => 'nullable|string|max:1000',
            'store_phone'                  => 'nullable|string|max:20',
            'store_address'                => 'nullable|string|max:500',
            'currency'                     => 'nullable|string|max:10',
            'language'                     => 'nullable|string|max:10',
            'timezone'                     => 'nullable|string|max:50',
            'order_notifications'          => 'nullable|boolean',
            'message_notifications'        => 'nullable|boolean',
            'low_stock_notifications'      => 'nullable|boolean',
            // Sound notification settings
            'sound_notifications_enabled'  => 'nullable|boolean',
            'sound_order'                  => 'nullable|string|max:50',
            'sound_message'                => 'nullable|string|max:50',
            'sound_comment'                => 'nullable|string|max:50',
            'sound_volume'                 => 'nullable|integer|min:0|max:100',
        ]);

        $user = Auth::user();

        $settings = $user->settings ?? [];
        $settings = array_merge($settings, $validated);
        $user->settings = $settings;
        $user->save();

        return redirect()->route('customer.settings.index')
            ->with('success', 'تم حفظ الإعدادات بنجاح');
    }
}
