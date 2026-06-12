<?php

namespace App\Services;

use App\Http\Controllers\Customer\AiSettingsController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Generate AI response for customer message.
     */
    public function generateResponse(User $user, string $customerMessage, ?string $conversationHistory = null): ?string
    {
        try {
            // Get user's AI settings
            $settings = AiSettingsController::getUserSettings($user->id);
            $model = $settings['ai_model'] ?? 'llama-3.3-70b-versatile';
            $isEnabled = $settings['ai_enabled'] ?? false;
            $autoReply = $settings['auto_reply'] ?? false;

            // Check if AI is enabled and auto-reply is on
            if (!$isEnabled || !$autoReply) {
                Log::info('AI disabled or auto-reply off for user: ' . $user->id);
                return null;
            }

            // Get user's business context
            $businessContext = $this->getBusinessContext($user);

            // Build the prompt
            $systemPrompt = $this->buildSystemPrompt($user, $settings, $businessContext);

            // Prepare messages for the AI
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $customerMessage
                ]
            ];

            // Make request to Groq API
            $response = Http::timeout($settings['ai_timeout'] ?? 15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.groq.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $settings['creativity'] ?? 0.7,
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $aiResponse = $data['choices'][0]['message']['content'] ?? null;

                if ($aiResponse) {
                    Log::info('AI response generated successfully for user: ' . $user->id);
                    return trim($aiResponse);
                }
            } else {
                Log::error('Groq API error: ' . $response->body());
            }

            return null;
        } catch (\Exception $e) {
            Log::error('AI Service error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build system prompt for the AI.
     */
    protected function buildSystemPrompt(User $user, array $settings, array $businessContext): string
    {
        $tone = $settings['response_tone'] ?? 'friendly';
        $language = $settings['language'] ?? 'ar';

        // Build business info section
        $businessInfo = "معلومات عن متجري:\n";
        $businessInfo .= "- اسم المتجر: " . ($user->business_name ?? $user->full_name ?? 'متجرنا') . "\n";

        if (!empty($businessContext['products'])) {
            $businessInfo .= "- المنتجات المتاحة:\n";
            foreach ($businessContext['products'] as $product) {
                $businessInfo .= "  * {$product['name']} - {$product['price']} دينار";
                if ($product['stock'] > 0) {
                    $businessInfo .= " (متوفر {$product['stock']} قطعة)";
                } else {
                    $businessInfo .= " (نفذت الكمية)";
                }
                $businessInfo .= "\n";
            }
        }

        // Build instructions based on tone
        $toneInstructions = match($tone) {
            'professional' => 'استخدم لغة احترافية ورسمية. كن دقيقاً ومباشراً في الإجابة.',
            'casual' => 'استخدم لغة عادية وبسيطة. كن ودوداً ومريحاً.',
            'friendly' => 'استخدم لغة ودودة ودافئة. كن متحمساً ومساعداً.',
            default => 'كن مفيداً وودوداً في ردودك.',
        };

        $prompt = <<<PROMPT
أنت مساعد ذكي لمتجر إلكتروني. مهمتك هي مساعدة العملاء والرد على استفساراتهم بطريقة احترافية.

{$businessInfo}

التعليمات:
- {$toneInstructions}
- أجب باللغة العربية فقط
- كن مختصراً (لا تتجاوز 150 كلمة)
- إذا سأل العميل عن منتج غير موجود في القائمة، اعتذر واعرض المنتجات المتاحة
- إذا سأل عن الأسعار، اذكر السعر من القائمة أعلاه
- إذا أراد الطلب، اطلب منه تفاصيل الطلب (اسم المنتج، الكمية، العنوان)
- كن مهذباً ومحترماً دائماً
- لا تخترع معلومات غير موجودة في سياق المتجر

PROMPT;

        return $prompt;
    }

    /**
     * Get business context (products, categories, etc).
     */
    protected function getBusinessContext(User $user): array
    {
        $products = Product::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get(['name', 'price', 'stock', 'description'])
            ->map(function ($product) {
                return [
                    'name' => $product->name,
                    'price' => number_format($product->price, 2),
                    'stock' => $product->stock ?? 0,
                    'description' => $product->description,
                ];
            })
            ->toArray();

        return [
            'products' => $products,
        ];
    }
}
