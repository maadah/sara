<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Services\AiProviderService;
use App\Services\StoreAssistantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiSettingsController extends Controller
{
    /**
     * Show AI settings page with usage statistics
     */
    public function index()
    {
        $user = Auth::user();
        $settings = $user->aiSetting ?? new AiSetting([
            'user_id' => $user->id,
            'system_instruction' => AiSetting::getDefaultSystemInstruction(),
            'ai_provider' => 'openai',
            'openai_model' => 'gpt-4.1-mini',
        ]);

        // Get all available models for both providers
        $allModels = AiProviderService::getAllModels();

        // Get usage statistics
        $usageStats = [
            'today' => AiUsageLog::getTodayUsage($user->id),
            'month' => AiUsageLog::getMonthUsage($user->id),
            'by_model' => AiUsageLog::getUsageByModel($user->id, 'month'),
            'by_type' => AiUsageLog::getUsageByType($user->id, 'month'),
            'daily' => AiUsageLog::getDailyUsage($user->id, 30),
        ];

        return view('customer.ai-settings.index', compact('settings', 'allModels', 'usageStats'));
    }

    /**
     * Update AI settings
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
            'send_product_images' => 'boolean',
            'use_assistant_mode' => 'boolean',
            'store_description' => 'nullable|string|max:2000',
            'store_policies' => 'nullable|string|max:2000',
            'greeting_message' => 'nullable|string|max:500',
            'session_timeout_minutes' => 'nullable|integer|min:5|max:120',
            'max_history_turns' => 'nullable|integer|min:3|max:20',
            'enable_upsell' => 'boolean',
            'enable_fast_replies' => 'boolean',
            'strict_store_scope' => 'boolean',
            'working_hours' => 'nullable|string|max:100',
            'delivery_time' => 'nullable|string|max:100',
            'delivery_cost' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();

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
            'send_product_images' => $request->boolean('send_product_images', true),
            'use_assistant_mode' => $request->boolean('use_assistant_mode'),
            'store_description' => $request->store_description,
            'store_policies' => $request->store_policies,
            'greeting_message' => $request->greeting_message,
            'session_timeout_minutes' => $request->session_timeout_minutes ?? 30,
            'max_history_turns' => $request->max_history_turns ?? 10,
            'enable_upsell' => $request->boolean('enable_upsell', true),
            'enable_fast_replies' => $request->boolean('enable_fast_replies', true),
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
            ['user_id' => $user->id],
            $updateData
        );

        return redirect()->route('customer.ai-settings.index')
            ->with('success', 'تم حفظ إعدادات الذكاء الاصطناعي بنجاح');
    }

    /**
     * Test AI connection (OpenAI or Groq based on settings)
     */
    public function testConnection(Request $request)
    {
        $user = Auth::user();
        $provider = new AiProviderService($user);
        $result = $provider->testConnection();

        return response()->json($result);
    }

    /**
     * Get available models for a specific provider
     */
    public function getModels(Request $request)
    {
        $provider = $request->get('provider', 'openai');
        $allModels = AiProviderService::getAllModels();

        return response()->json([
            'success' => true,
            'models' => $allModels[$provider] ?? [],
        ]);
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(Request $request)
    {
        $user = Auth::user();
        $period = $request->get('period', 'month');

        $stats = [
            'today' => AiUsageLog::getTodayUsage($user->id),
            'month' => AiUsageLog::getMonthUsage($user->id),
            'by_model' => AiUsageLog::getUsageByModel($user->id, $period),
            'by_type' => AiUsageLog::getUsageByType($user->id, $period),
            'daily' => AiUsageLog::getDailyUsage($user->id, 30),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Reset to default settings (now with OpenAI as default)
     */
    public function resetToDefault()
    {
        $user = Auth::user();

        AiSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'ai_provider' => 'openai',
                'openai_model' => 'gpt-4.1-mini',
                'groq_model' => 'llama-3.3-70b-versatile',
                'ai_enabled' => false,
                'system_instruction' => AiSetting::getDefaultSystemInstruction(),
                'temperature' => 0.2,
                'top_p' => 0.95,
                'top_k' => 40,
                'max_output_tokens' => 350,
                'auto_reply_enabled' => true,
                'collect_customer_info' => true,
                'can_create_orders' => true,
                'store_description' => null,
                'store_policies' => null,
                'greeting_message' => null,
                'session_timeout_minutes' => 30,
                'max_history_turns' => 10,
                'enable_upsell' => true,
                'enable_fast_replies' => true,
                'strict_store_scope' => true,
                'working_hours' => '9am - 10pm',
                'delivery_time' => 'نفس اليوم',
                'delivery_cost' => 5000,
            ]
        );

        return redirect()->route('customer.ai-settings.index')
            ->with('success', 'تم إعادة الإعدادات للقيم الافتراضية');
    }

    /**
     * Sync store assistant with OpenAI
     */
    public function syncAssistant(Request $request)
    {
        $user = Auth::user();
        $manager = new StoreAssistantManager();

        $result = $manager->syncStoreAssistant($user);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'تم مزامنة المساعد الذكي بنجاح',
                'assistant_id' => $result['assistant_id'],
                'products_count' => $result['products_count'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'فشل في مزامنة المساعد: ' . ($result['error'] ?? 'خطأ غير معروف'),
        ], 400);
    }

    /**
     * Delete store assistant
     */
    public function deleteAssistant(Request $request)
    {
        $user = Auth::user();
        $manager = new StoreAssistantManager();

        $result = $manager->deleteStoreAssistant($user);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المساعد الذكي بنجاح',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'فشل في حذف المساعد: ' . ($result['error'] ?? 'خطأ غير معروف'),
        ], 400);
    }
}
