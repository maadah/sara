<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\AiProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiSettingsController extends Controller
{
    /**
     * Show AI settings page with usage statistics for all stores
     */
    public function index()
    {
        $admin = Auth::user();

        // Get admin's own AI settings (for global/default settings)
        $settings = $admin->aiSetting ?? new AiSetting([
            'user_id' => $admin->id,
            'system_instruction' => AiSetting::getDefaultSystemInstruction(),
            'ai_provider' => 'openai',
            'openai_model' => 'gpt-4.1-mini',
        ]);

        // Get all available models for both providers
        $allModels = AiProviderService::getAllModels();

        // Get usage statistics for ALL stores (admin view)
        $usageStats = [
            'today' => AiUsageLog::getTodayUsage(null), // null = all users
            'month' => AiUsageLog::getMonthUsage(null),
            'by_model' => AiUsageLog::getUsageByModel(null, 'month'),
            'by_type' => AiUsageLog::getUsageByType(null, 'month'),
            'daily' => AiUsageLog::getDailyUsage(null, 30),
            'by_store' => AiUsageLog::getAllStoresUsage('month'),
        ];

        // Get stores with their usage
        $stores = User::where('role', 'customer')
            ->where('status', 'approved')
            ->with('aiSetting')
            ->get()
            ->map(function ($store) {
                $storeUsage = AiUsageLog::getMonthUsage($store->id);
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'store_name' => $store->store_name ?? $store->name,
                    'ai_enabled' => $store->aiSetting->ai_enabled ?? false,
                    'ai_provider' => $store->aiSetting->ai_provider ?? 'openai',
                    'ai_model' => $store->aiSetting->openai_model ?? $store->aiSetting->groq_model ?? 'gpt-4.1-mini',
                    'requests' => $storeUsage['total_requests'] ?? 0,
                    'tokens' => $storeUsage['total_tokens'] ?? 0,
                    'cost' => $storeUsage['total_cost'] ?? 0,
                ];
            });

        return view('admin.ai-settings.index', compact('settings', 'allModels', 'usageStats', 'stores'));
    }

    /**
     * Update admin AI settings (global defaults)
     */
    public function update(Request $request)
    {
        $request->validate([
            'ai_provider' => 'required|string|in:openai,groq',
            'openai_api_key' => 'nullable|string',
            'openai_model' => 'nullable|string',
            'groq_api_key' => 'nullable|string',
            'groq_model' => 'nullable|string',
            'ai_enabled' => 'boolean',
            'system_instruction' => 'nullable|string|max:5000',
            'temperature' => 'required|numeric|min:0|max:2',
            'top_p' => 'required|numeric|min:0|max:1',
            'top_k' => 'required|integer|min:1|max:100',
            'max_output_tokens' => 'required|integer|min:100|max:8192',
            'auto_reply_enabled' => 'boolean',
            'collect_customer_info' => 'boolean',
            'can_create_orders' => 'boolean',
            'store_description' => 'nullable|string|max:2000',
            'store_policies' => 'nullable|string|max:2000',
            'greeting_message' => 'nullable|string|max:500',
            'strict_store_scope' => 'boolean',
            'working_hours' => 'nullable|string|max:100',
            'delivery_time' => 'nullable|string|max:100',
            'delivery_cost' => 'nullable|integer|min:0',
        ]);

        $admin = Auth::user();

        $updateData = [
            'ai_provider' => $request->ai_provider,
            'openai_model' => $request->openai_model ?? 'gpt-4.1-mini',
            'groq_model' => $request->groq_model ?? 'llama-3.3-70b-versatile',
            'ai_enabled' => $request->boolean('ai_enabled'),
            'system_instruction' => $request->system_instruction,
            'temperature' => $request->temperature,
            'top_p' => $request->top_p,
            'top_k' => $request->top_k,
            'max_output_tokens' => $request->max_output_tokens,
            'auto_reply_enabled' => $request->boolean('auto_reply_enabled'),
            'collect_customer_info' => $request->boolean('collect_customer_info'),
            'can_create_orders' => $request->boolean('can_create_orders'),
            'store_description' => $request->store_description,
            'store_policies' => $request->store_policies,
            'greeting_message' => $request->greeting_message,
            'strict_store_scope' => $request->boolean('strict_store_scope', true),
            'working_hours' => $request->working_hours,
            'delivery_time' => $request->delivery_time ?? 'نفس اليوم',
            'delivery_cost' => $request->delivery_cost ?? 5000,
        ];

        // Only update API keys if provided (don't overwrite with empty)
        if ($request->filled('openai_api_key')) {
            $updateData['openai_api_key'] = $request->openai_api_key;
        }
        if ($request->filled('groq_api_key')) {
            $updateData['groq_api_key'] = $request->groq_api_key;
        }

        AiSetting::updateOrCreate(
            ['user_id' => $admin->id],
            $updateData
        );

        return redirect()->route('admin.ai-settings.index')
            ->with('success', 'تم حفظ إعدادات الذكاء الاصطناعي بنجاح');
    }

    /**
     * Test AI connection
     */
    public function testConnection(Request $request)
    {
        $admin = Auth::user();
        $provider = new AiProviderService($admin);
        $result = $provider->testConnection();

        return response()->json($result);
    }

    /**
     * Get usage statistics for all stores or specific store
     */
    public function getUsageStats(Request $request)
    {
        $storeId = $request->get('store_id');
        $period = $request->get('period', 'month');

        $stats = [
            'today' => AiUsageLog::getTodayUsage($storeId),
            'month' => AiUsageLog::getMonthUsage($storeId),
            'by_model' => AiUsageLog::getUsageByModel($storeId, $period),
            'by_type' => AiUsageLog::getUsageByType($storeId, $period),
            'daily' => AiUsageLog::getDailyUsage($storeId, 30),
        ];

        if (!$storeId) {
            $stats['by_store'] = AiUsageLog::getAllStoresUsage($period);
        }

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * View specific store's AI settings
     */
    public function showStore($storeId)
    {
        $store = User::where('role', 'customer')->findOrFail($storeId);
        $settings = $store->aiSetting ?? new AiSetting([
            'user_id' => $store->id,
            'ai_provider' => 'openai',
            'openai_model' => 'gpt-4.1-mini',
        ]);

        $allModels = AiProviderService::getAllModels();

        $usageStats = [
            'today' => AiUsageLog::getTodayUsage($store->id),
            'month' => AiUsageLog::getMonthUsage($store->id),
            'by_model' => AiUsageLog::getUsageByModel($store->id, 'month'),
            'by_type' => AiUsageLog::getUsageByType($store->id, 'month'),
            'daily' => AiUsageLog::getDailyUsage($store->id, 30),
        ];

        return view('admin.ai-settings.store', compact('store', 'settings', 'allModels', 'usageStats'));
    }

    /**
     * Update specific store's AI settings
     */
    public function updateStore(Request $request, $storeId)
    {
        $store = User::where('role', 'customer')->findOrFail($storeId);

        $request->validate([
            'ai_provider' => 'required|string|in:openai,groq',
            'openai_model' => 'nullable|string',
            'groq_model' => 'nullable|string',
            'ai_enabled' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'use_assistant_mode' => 'boolean',
        ]);

        AiSetting::updateOrCreate(
            ['user_id' => $store->id],
            [
                'ai_provider' => $request->ai_provider,
                'openai_model' => $request->openai_model ?? 'gpt-4.1-mini',
                'groq_model' => $request->groq_model ?? 'llama-3.3-70b-versatile',
                'ai_enabled' => $request->boolean('ai_enabled'),
                'auto_reply_enabled' => $request->boolean('auto_reply_enabled'),
                'use_assistant_mode' => $request->boolean('use_assistant_mode'),
            ]
        );

        return redirect()->route('admin.ai-settings.store', $storeId)
            ->with('success', 'تم تحديث إعدادات المتجر بنجاح');
    }

    /**
     * Reset to default settings
     */
    public function resetToDefault()
    {
        $admin = Auth::user();

        AiSetting::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'ai_provider' => 'openai',
                'openai_model' => 'gpt-4.1-mini',
                'groq_model' => 'llama-3.3-70b-versatile',
                'ai_enabled' => true,
                'system_instruction' => AiSetting::getDefaultSystemInstruction(),
                'temperature' => 0.3,
                'top_p' => 0.95,
                'top_k' => 40,
                'max_output_tokens' => 500,
                'auto_reply_enabled' => true,
                'collect_customer_info' => true,
                'can_create_orders' => true,
                'strict_store_scope' => true,
            ]
        );

        return redirect()->route('admin.ai-settings.index')
            ->with('success', 'تمت استعادة الإعدادات الافتراضية');
    }
}
