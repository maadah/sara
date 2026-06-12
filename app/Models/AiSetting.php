<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSetting extends Model
{
    protected $fillable = [
        'user_id',
        'ai_api_url',
        'ai_model',
        'ai_enabled',
        'ai_provider',
        'system_instruction',
        'temperature',
        'top_p',
        'top_k',
        'max_output_tokens',
        'auto_reply_enabled',
        'collect_customer_info',
        'can_create_orders',
        'store_description',
        'store_policies',
        'greeting_message',
        'fallback_comment_reply',
        // OpenAI API settings
        'openai_api_key',
        'openai_model',
        'use_assistant_mode', // NEW: Enable OpenAI Assistants API for ~40% token savings
        // OpenAI Assistant fields
        'openai_assistant_id',
        'openai_vector_store_id',
        'openai_file_id',
        'assistant_synced_at',
        // Groq API settings (kept for backward compatibility)
        'groq_api_key',
        'groq_model',
        'session_timeout_minutes',
        'max_history_turns',
        'enable_upsell',
        'enable_fast_replies',
        'strict_store_scope',
        'working_hours',
        'delivery_time',
        'delivery_cost',
        // Image settings
        'send_product_images',
        // Store contact info
        'contact_phone',
        'store_location',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'collect_customer_info' => 'boolean',
        'can_create_orders' => 'boolean',
        'enable_upsell' => 'boolean',
        'enable_fast_replies' => 'boolean',
        'strict_store_scope' => 'boolean',
        'send_product_images' => 'boolean',
        'use_assistant_mode' => 'boolean',
        'temperature' => 'decimal:2',
        'top_p' => 'decimal:2',
    ];

    /**
     * Hide sensitive fields
     */
    protected $hidden = [
        'groq_api_key',
        'openai_api_key',
    ];

    /**
     * Available AI models (Groq)
     */
    public static array $availableModels = [
        [
            'id' => 'llama-3.3-70b-versatile',
            'name' => 'Llama 3.3 70B Versatile',
            'description' => 'أقوى نموذج - مناسب للمحادثات المعقدة',
        ],
        [
            'id' => 'llama-3.1-70b-versatile',
            'name' => 'Llama 3.1 70B Versatile',
            'description' => 'نموذج قوي ومستقر',
        ],
        [
            'id' => 'llama-3.1-8b-instant',
            'name' => 'Llama 3.1 8B Instant',
            'description' => 'سريع جداً - للردود البسيطة',
        ],
        [
            'id' => 'mixtral-8x7b-32768',
            'name' => 'Mixtral 8x7B',
            'description' => 'توازن بين السرعة والجودة',
        ],
        [
            'id' => 'gemma2-9b-it',
            'name' => 'Gemma 2 9B',
            'description' => 'نموذج Google خفيف',
        ],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default system instruction for Iraqi store
     */
    public static function getDefaultSystemInstruction(): string
    {
        return <<<INSTRUCTION
انت مساعد ذكي لمتجر عراقي. مهمتك:

1. **الترحيب**: رحب بالزبون بشكل ودي باللهجة العراقية
2. **المنتجات**: اعرض المنتجات المتوفرة واجب على استفسارات الزبون
3. **المساعدة**: ساعد الزبون في اختيار المنتج المناسب
4. **الإقناع**: إذا لاحظت تردد الزبون، حاول إقناعه بلطف
5. **الطلب**: عند رغبة الزبون بالشراء، اجمع معلوماته:
   - الاسم الكامل
   - رقم الهاتف
   - العنوان بالتفصيل (المحافظة، المنطقة، أقرب نقطة دالة)

**ملاحظات مهمة:**
- استخدم اللهجة العراقية الودية
- كن مختصراً وواضحاً
- لا تكذب على الزبون
- إذا لم تعرف شيء، قل "خليني أسأل واردلك"
- أسعار المنتجات بالدينار العراقي (IQD)
INSTRUCTION;
    }
}
