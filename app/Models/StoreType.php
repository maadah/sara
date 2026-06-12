<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreType extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'display_name_en',
        'required_attributes',
        'optional_attributes',
        'ai_template',
        'order_questions',
        'requires_stock',
        'is_active',
    ];

    protected $casts = [
        'required_attributes' => 'array',
        'optional_attributes' => 'array',
        'requires_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Predefined store types with their attribute requirements
     */
    public const TYPES = [
        'clothing' => [
            'display_name' => 'متجر ملابس',
            'display_name_en' => 'Clothing Store',
            'required_attributes' => ['size', 'color'],
            'optional_attributes' => ['material', 'brand', 'style'],
            'ai_template' => 'عند طلب ملابس، اسأل عن المقاس واللون قبل إتمام الطلب. المقاسات المتوفرة: S, M, L, XL, XXL',
            'order_questions' => ['شنو المقاس؟', 'شنو اللون؟'],
            'requires_stock' => true,
        ],
        'electronics' => [
            'display_name' => 'متجر إلكترونيات',
            'display_name_en' => 'Electronics Store',
            'required_attributes' => [],
            'optional_attributes' => ['warranty', 'model', 'specs', 'brand'],
            'ai_template' => 'قدم معلومات عن المواصفات والضمان. اذكر فترة الضمان إن وجدت.',
            'order_questions' => [],
            'requires_stock' => true,
        ],
        'food' => [
            'display_name' => 'مطعم / طعام',
            'display_name_en' => 'Restaurant / Food',
            'required_attributes' => [],
            'optional_attributes' => ['spice_level', 'ingredients', 'allergens'],
            'ai_template' => 'اسأل عن الحساسية الغذائية. وضح وقت التحضير التقريبي.',
            'order_questions' => ['عندك حساسية من شي؟'],
            'requires_stock' => false,
        ],
        'cosmetics' => [
            'display_name' => 'متجر مستحضرات تجميل',
            'display_name_en' => 'Cosmetics Store',
            'required_attributes' => [],
            'optional_attributes' => ['skin_type', 'ingredients', 'brand', 'shade'],
            'ai_template' => 'اسأل عن نوع البشرة للمنتجات المناسبة. اذكر مكونات المنتج الأساسية.',
            'order_questions' => ['شنو نوع بشرتك؟'],
            'requires_stock' => true,
        ],
        'medical' => [
            'display_name' => 'صيدلية / مستلزمات طبية',
            'display_name_en' => 'Pharmacy / Medical Supplies',
            'required_attributes' => [],
            'optional_attributes' => ['prescription_required', 'dosage', 'brand'],
            'ai_template' => 'تنبيه: لا تقدم نصائح طبية. وجه العميل للطبيب عند الحاجة. اذكر إن كان المنتج يحتاج وصفة طبية.',
            'order_questions' => [],
            'requires_stock' => true,
        ],
        'jewelry' => [
            'display_name' => 'متجر مجوهرات',
            'display_name_en' => 'Jewelry Store',
            'required_attributes' => ['size'],
            'optional_attributes' => ['material', 'karat', 'stone'],
            'ai_template' => 'اسأل عن مقاس الخاتم/السوار. وضح نوع المعدن والعيار.',
            'order_questions' => ['شنو المقاس؟'],
            'requires_stock' => true,
        ],
        'services' => [
            'display_name' => 'خدمات',
            'display_name_en' => 'Services',
            'required_attributes' => ['datetime'],
            'optional_attributes' => ['duration', 'location', 'service_type'],
            'ai_template' => 'اسأل عن الموعد المناسب. تأكد من التوفر قبل الحجز.',
            'order_questions' => ['شنو الموعد المناسب؟'],
            'requires_stock' => false,
        ],
        'general' => [
            'display_name' => 'متجر عام',
            'display_name_en' => 'General Store',
            'required_attributes' => [],
            'optional_attributes' => [],
            'ai_template' => '',
            'order_questions' => [],
            'requires_stock' => false,
        ],
    ];

    /**
     * Attribute display names in Arabic
     */
    public const ATTRIBUTE_NAMES = [
        'size' => 'المقاس',
        'color' => 'اللون',
        'material' => 'الخامة',
        'brand' => 'الماركة',
        'style' => 'الستايل',
        'warranty' => 'الضمان',
        'model' => 'الموديل',
        'specs' => 'المواصفات',
        'spice_level' => 'درجة الحرارة',
        'ingredients' => 'المكونات',
        'allergens' => 'المسببات للحساسية',
        'skin_type' => 'نوع البشرة',
        'shade' => 'الدرجة',
        'prescription_required' => 'يحتاج وصفة',
        'dosage' => 'الجرعة',
        'karat' => 'العيار',
        'stone' => 'الحجر',
        'datetime' => 'الموعد',
        'duration' => 'المدة',
        'location' => 'الموقع',
        'service_type' => 'نوع الخدمة',
    ];

    /**
     * Size options for different store types
     */
    public const SIZE_OPTIONS = [
        'clothing' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'],
        'jewelry' => ['5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'],
        'shoes' => ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'],
    ];

    /**
     * Common color options
     */
    public const COLOR_OPTIONS = [
        'red' => 'أحمر',
        'blue' => 'أزرق',
        'green' => 'أخضر',
        'black' => 'أسود',
        'white' => 'أبيض',
        'yellow' => 'أصفر',
        'pink' => 'وردي',
        'purple' => 'بنفسجي',
        'brown' => 'بني',
        'gray' => 'رمادي',
        'orange' => 'برتقالي',
        'beige' => 'بيج',
        'navy' => 'كحلي',
        'gold' => 'ذهبي',
        'silver' => 'فضي',
    ];

    /**
     * Get stores using this type
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get Arabic name for an attribute
     */
    public static function getAttributeName(string $key): string
    {
        return self::ATTRIBUTE_NAMES[$key] ?? $key;
    }

    /**
     * Get color name in Arabic
     */
    public static function getColorName(string $color): string
    {
        return self::COLOR_OPTIONS[strtolower($color)] ?? $color;
    }

    /**
     * Check if store type requires a specific attribute
     */
    public function requiresAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->required_attributes ?? []);
    }

    /**
     * Get all possible attributes for this store type
     */
    public function getAllAttributes(): array
    {
        return array_merge(
            $this->required_attributes ?? [],
            $this->optional_attributes ?? []
        );
    }
}
