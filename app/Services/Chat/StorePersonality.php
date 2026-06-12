<?php

namespace App\Services\Chat;

/**
 * StorePersonality — returns conversation-style hints based on the store type.
 *
 * Different store categories (clothing, electronics, food, etc.) need
 * different selling vocabulary, upsell strategies, and clarifying questions.
 * These hints are injected into the system prompt dynamically.
 */
class StorePersonality
{
    /**
     * Get personality hints block for injection into system prompt.
     *
     * @param string $storeType  The store_types.name value (e.g. 'clothing', 'electronics').
     */
    public function getHints(string $storeType): string
    {
        $hints = $this->resolveHints($storeType);

        if (empty($hints)) {
            return '';
        }

        $focusLine     = $hints['focus']     ? 'التركيز: ' . $hints['focus'] : '';
        $upsellLine    = $hints['upsell']    ? 'فرص البيع الإضافي: ' . $hints['upsell'] : '';
        $questionsLine = $hints['questions']  ? 'أسئلة مقترحة: ' . implode(' / ', $hints['questions']) : '';

        return <<<BLOCK

[شخصية المتجر — {$storeType}]
{$focusLine}
{$upsellLine}
{$questionsLine}
BLOCK;
    }

    /**
     * Resolve raw hints array for a given store type.
     *
     * @return array{focus: string, upsell: string, questions: string[]}
     */
    private function resolveHints(string $storeType): array
    {
        return match ($storeType) {
            'clothing' => [
                'focus'     => 'الستايل، المقاس، تنسيق الألوان، المناسبات',
                'upsell'    => 'إكسسوارات، قطع متناسقة',
                'questions' => [
                    'شنو المناسبة؟',
                    'شنو مقاسك؟',
                    'تحب الالوان الفاتحة ولا الغامقة؟',
                ],
            ],

            'electronics' => [
                'focus'     => 'المواصفات، الكفالة، التوافق',
                'upsell'    => 'كفرات، شواحن، إكسسوارات',
                'questions' => [
                    'لأي جهاز تبي الكيبل؟',
                    'شنو ميزانيتك؟',
                    'تريده للاستخدام الشخصي أو الشغل؟',
                ],
            ],

            'food' => [
                'focus'     => 'الطزاجة، المكونات، الحصص',
                'upsell'    => 'مشروبات، حلويات، إضافات',
                'questions' => [
                    'تبي توصيل ولا تجيب؟',
                    'اكو حساسية من اي اكل؟',
                    'لكم شخص الطلب؟',
                ],
            ],

            'cosmetics' => [
                'focus'     => 'نوع البشرة، المكونات، النتائج',
                'upsell'    => 'منتجات مكملة، سيت كامل',
                'questions' => [
                    'شنو نوع بشرتك؟',
                    'استخدمتي هالماركة قبل؟',
                    'تبين للاستخدام اليومي ولا للمناسبات؟',
                ],
            ],

            'jewelry' => [
                'focus'     => 'الخامة، التصميم، المناسبة',
                'upsell'    => 'قطع متناسقة، علب هدايا',
                'questions' => [
                    'هدية ولا لنفسك؟',
                    'تحبين الذهب ولا الفضة؟',
                    'شنو المناسبة؟',
                ],
            ],

            'medical' => [
                'focus'     => 'الاستخدام، التعليمات، الموثوقية',
                'upsell'    => 'منتجات مكملة',
                'questions' => [
                    'عندك وصفة طبية؟',
                    'الاستخدام لشخص كبير ولا صغير؟',
                ],
            ],

            'furniture' => [
                'focus'     => 'الأبعاد، الخامة، اللون',
                'upsell'    => 'قطع متناسقة، إكسسوارات منزلية',
                'questions' => [
                    'شنو لون غرفتك؟',
                    'شنو القياس المطلوب؟',
                    'الشقة ولا بيت؟',
                ],
            ],

            default => [
                'focus'     => '',
                'upsell'    => '',
                'questions' => [],
            ],
        };
    }
}
