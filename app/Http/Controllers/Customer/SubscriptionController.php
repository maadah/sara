<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Show subscription page
     */
    public function index()
    {
        $user = auth()->user();

        // Get current subscription
        $currentSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        // Define available plans
        $plans = [
            [
                'id' => 'basic',
                'name' => 'الباقة الأساسية',
                'price' => 19,
                'currency' => 'USD',
                'period' => 'شهرياً',
                'features' => [
                    ['text' => 'رد تلقائي على رسائل الفيس بوك', 'included' => true],
                    ['text' => 'رد تلقائي على رسائل الانستقرام', 'included' => true],
                    ['text' => 'الرد على 1000 رسالة شهرياً', 'included' => true],
                    ['text' => 'دعم فني 3 ساعات اسبوعياً', 'included' => true],
                    ['text' => 'الرد على التعليقات', 'included' => true],
                    ['text' => 'تقرير شهري بسيط', 'included' => true],
                    ['text' => 'مخزن المنتجات', 'included' => true],
                    ['text' => 'دعم مخصص', 'included' => false],
                    ['text' => 'رد على الواتساب', 'included' => false],
                ],
                'is_popular' => false,
            ],
            [
                'id' => 'advanced',
                'name' => 'الباقة المتقدمة',
                'price' => 29,
                'currency' => 'USD',
                'period' => 'شهرياً',
                'features' => [
                    ['text' => 'رد تلقائي على رسائل الفيس بوك', 'included' => true],
                    ['text' => 'رد تلقائي على رسائل الانستقرام', 'included' => true],
                    ['text' => 'الرد على 3000 رسالة شهرياً', 'included' => true],
                    ['text' => 'دعم فني 5 ساعات اسبوعياً', 'included' => true],
                    ['text' => 'الرد على التعليقات', 'included' => true],
                    ['text' => 'تقرير شهري بسيط', 'included' => true],
                    ['text' => 'مخزن المنتجات', 'included' => true],
                    ['text' => 'دعم مخصص', 'included' => false],
                    ['text' => 'رد على الواتساب', 'included' => true],
                ],
                'is_popular' => true,
            ],
            [
                'id' => 'professional',
                'name' => 'الباقة الاحترافية',
                'price' => 39,
                'currency' => 'USD',
                'period' => 'شهرياً',
                'features' => [
                    ['text' => 'رد تلقائي على رسائل الفيس بوك', 'included' => true],
                    ['text' => 'رد تلقائي على رسائل الانستقرام', 'included' => true],
                    ['text' => 'الرد على 5000 رسالة شهرياً', 'included' => true],
                    ['text' => 'دعم فني 5 ساعات اسبوعياً', 'included' => true],
                    ['text' => 'الرد على التعليقات', 'included' => true],
                    ['text' => 'تقرير شهري مفصل', 'included' => true],
                    ['text' => 'مخزن المنتجات', 'included' => true],
                    ['text' => 'دعم مخصص', 'included' => true],
                    ['text' => 'رد على الواتساب', 'included' => true],
                ],
                'is_popular' => false,
            ],
        ];

        return view('customer.subscription.index', compact('user', 'currentSubscription', 'plans'));
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,advanced,professional',
        ]);

        // In a real app, this would integrate with a payment gateway
        // For now, we'll just show a message

        return back()->with('info', 'سيتم التواصل معك لإتمام عملية الاشتراك');
    }
}
