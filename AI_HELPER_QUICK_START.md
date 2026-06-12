# 🚀 AI-Helper - Quick Start Guide
## دليل البدء السريع

---

## ✅ النظام جاهز! ماذا تفعل الآن؟

### 1️⃣ افتح Dashboard
```
URL: http://your-domain.com/customer/ai-helper
```

أو من sidebar:
- ابحث عن "مساعد AI" 🤖
- اضغط عليه

---

## 2️⃣ أول 3 خطوات

### الخطوة 1: أضف أول سؤال لقاعدة المعرفة

```php
// يمكنك إضافته من كود PHP أو انتظر UI
use App\Models\AiKnowledgeBase;

AiKnowledgeBase::create([
    'user_id' => auth()->id(),
    'question' => 'شنو سعر التوصيل؟',
    'answer' => 'سعر التوصيل 5,000 دينار عراقي لجميع أنحاء العراق. التوصيل خلال 1-3 أيام.',
    'category' => 'delivery',
    'keywords' => ['توصيل', 'سعر', 'دينار', 'وقت'],
    'status' => 'active',
    'is_verified' => true,
    'priority' => 10,
]);
```

### الخطوة 2: سجّل سؤال بدون إجابة (للتجربة)

```php
use App\Models\UnansweredQuestion;

UnansweredQuestion::create([
    'user_id' => auth()->id(),
    'question' => 'هل تقبلون الدفع بالفيزا؟',
    'category' => 'payment',
    'status' => 'pending',
    'is_reviewed' => false,
    'needs_urgent_attention' => false,
    'occurrence_count' => 1,
]);
```

### الخطوة 3: اذهب للـ Dashboard وشوف النتائج!

```
/customer/ai-helper
```

ستشاهد:
- ✅ الإحصائيات
- ✅ السؤال المعلق (1)
- ✅ أزرار الإجراءات السريعة

---

## 🎯 الأمور المهمة

### Routes الجاهزة:

#### Dashboard:
```
GET /customer/ai-helper
```

#### Knowledge Base:
```
GET  /customer/ai-helper/knowledge-base        # قائمة
GET  /customer/ai-helper/knowledge-base/create # إضافة جديد
POST /customer/ai-helper/knowledge-base        # حفظ
GET  /customer/ai-helper/knowledge-base/{id}/edit  # تعديل
PUT  /customer/ai-helper/knowledge-base/{id}   # حفظ التعديل
DELETE /customer/ai-helper/knowledge-base/{id} # حذف
```

#### Unanswered Questions:
```
GET  /customer/ai-helper/unanswered           # قائمة
GET  /customer/ai-helper/unanswered/{id}      # عرض + إجابة
POST /customer/ai-helper/unanswered/{id}/answer    # حفظ الإجابة
POST /customer/ai-helper/unanswered/{id}/ignore    # تجاهل
```

---

## 💡 نصائح سريعة

### 1. كيف أضيف الكثير من الأسئلة بسرعة؟

استخدم Seeder:

```php
// database/seeders/KnowledgeBaseSeeder.php
use App\Models\AiKnowledgeBase;

$questions = [
    [
        'question' => 'شنو سعر التوصيل؟',
        'answer' => 'سعر التوصيل 5,000 دينار',
        'category' => 'delivery',
    ],
    [
        'question' => 'يمته يوصل الطلب؟',
        'answer' => 'الطلب يوصل خلال 1-3 أيام',
        'category' => 'delivery',
    ],
    [
        'question' => 'شنو طرق الدفع المتوفرة؟',
        'answer' => 'نقبل الدفع فيزا، زين كاش، كي كارد، ودفع عند الاستلام',
        'category' => 'payment',
    ],
];

foreach ($questions as $q) {
    AiKnowledgeBase::create([
        'user_id' => 1, // غير الـ ID
        'question' => $q['question'],
        'answer' => $q['answer'],
        'category' => $q['category'],
        'keywords' => AiKnowledgeBase::extractKeywords($q['question']),
        'status' => 'active',
        'is_verified' => true,
        'priority' => 5,
    ]);
}
```

ثم شغّل:
```bash
php artisan db:seed --class=KnowledgeBaseSeeder
```

### 2. كيف أختبر النظام؟

```php
// في Tinker
php artisan tinker

// إنشاء أسئلة معلقة للاختبار
$userId = 1; // user ID مالتك

for ($i = 1; $i <= 5; $i++) {
    \App\Models\UnansweredQuestion::create([
        'user_id' => $userId,
        'question' => "سؤال اختبار رقم {$i}",
        'category' => 'test',
        'status' => 'pending',
        'is_reviewed' => false,
        'occurrence_count' => rand(1, 5),
    ]);
}

// تحقق من العدد
\App\Models\UnansweredQuestion::getPendingCount($userId);
```

### 3. كيف أضيف Categories مخصصة؟

Categories الأساسية:
- `delivery` - توصيل
- `payment` - دفع
- `products` - منتجات
- `returns` - استرجاع
- `general` - عام

يمكنك إضافة أي category تريد!

---

## 🔧 التخصيص السريع

### تغيير الألوان في Dashboard:

افتح `/resources/views/admin/ai-helper/index.blade.php`

ابحث عن:
```css
.stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

غيّر الألوان حسب ذوقك!

### إضافة تصنيف جديد:

```php
// في Controller أو Model
$categories = [
    'delivery' => 'التوصيل',
    'payment' => 'الدفع',
    'products' => 'المنتجات',
    'returns' => 'الاسترجاع',
    'support' => 'الدعم الفني',  // جديد!
    'offers' => 'العروض',         // جديد!
];
```

---

## 📊 المراقبة والإحصائيات

### الحصول على إحصائيات:

```php
$userId = auth()->id();

$stats = [
    'total_kb' => \App\Models\AiKnowledgeBase::where('user_id', $userId)->count(),
    'active_kb' => \App\Models\AiKnowledgeBase::where('user_id', $userId)->active()->count(),
    'pending_q' => \App\Models\UnansweredQuestion::getPendingCount($userId),
    'answered_q' => \App\Models\UnansweredQuestion::where('user_id', $userId)
                        ->where('status', 'answered')->count(),
];
```

### أكثر الأسئلة استخداماً:

```php
$top = \App\Models\AiKnowledgeBase::where('user_id', auth()->id())
    ->orderBy('usage_count', 'desc')
    ->limit(10)
    ->get();
```

---

## ⚡ الإجراءات السريعة

### إضافة سؤال سريع:
```php
\App\Models\AiKnowledgeBase::create([
    'user_id' => auth()->id(),
    'question' => 'السؤال؟',
    'answer' => 'الإجابة',
    'category' => 'delivery',
    'keywords' => ['كلمة1', 'كلمة2'],
    'status' => 'active',
    'is_verified' => true,
]);
```

### الرد على سؤال معلق:
```php
$question = \App\Models\UnansweredQuestion::find($id);
$question->markAsAnswered('الإجابة هنا', auth()->id());

// نقل لقاعدة المعرفة
$kb = $question->addToKnowledgeBase(['priority' => 10]);
```

### البحث عن أسئلة مشابهة:
```php
$similar = \App\Models\AiKnowledgeBase::findSimilar(
    'شنو سعر التوصيل؟',
    auth()->id(),
    5  // عدد النتائج
);
```

---

## 🎓 أمثلة كاملة

### مثال 1: سير عمل كامل

```php
// 1. عميل يسأل سؤال جديد
$question = \App\Models\UnansweredQuestion::findOrCreate([
    'user_id' => 1,
    'question' => 'هل تقبلون الدفع بالفيزا؟',
    'category' => 'payment',
]);

// 2. الأدمن يشوف السؤال في Dashboard
// يفتح /customer/ai-helper

// 3. يكتب إجابة
$question->markAsAnswered(
    'نعم نقبل الدفع بالفيزا وزين كاش وكي كارد',
    auth()->id()
);

// 4. يضيفه لقاعدة المعرفة
$kb = $question->addToKnowledgeBase([
    'priority' => 8,
    'category' => 'payment',
]);

// 5. الآن AI يعرف الإجابة!
// المرة القادمة سيجيب تلقائياً ✅
```

---

## 🆘 حل المشاكل الشائعة

### المشكلة: لا أرى الأسئلة المعلقة

**الحل:**
```php
// تأكد من وجود أسئلة
\App\Models\UnansweredQuestion::where('user_id', auth()->id())->count();

// إنشاء سؤال للاختبار
\App\Models\UnansweredQuestion::create([
    'user_id' => auth()->id(),
    'question' => 'سؤال اختبار',
    'status' => 'pending',
    'is_reviewed' => false,
]);
```

### المشكلة: Badge لا يظهر في Sidebar

**الحل:**
```bash
# امسح الـ cache
php artisan view:clear
php artisan cache:clear

# تحديث الصفحة
```

---

## 📚 التوثيق الكامل

للمزيد من التفاصيل:
- `AI_HELPER_COMPLETE_SUMMARY.md` - ملخص شامل
- `AI_HELPER_SYSTEM_DOCUMENTATION.md` - توثيق تقني
- `AI_HELPER_INSTALLATION.md` - دليل التثبيت

---

## 🎉 مبروك!

النظام جاهز ويعمل! ابدأ باستخدامه الآن:

```
👉 /customer/ai-helper
```

**Happy Coding! 🚀**
