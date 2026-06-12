# إعداد نظام الرسائل (Meta Webhooks)

## نظرة عامة

تم إعداد نظام الرسائل لاستقبال رسائل Facebook Messenger و Instagram Direct عبر Meta Webhooks.

## المكونات

### Meta Webhooks
- استقبال الرسائل الواردة من Facebook و Instagram
- رابط الـ Webhook: `https://rihla.najmattalraafiden.com/webhooks/meta`
- الواجهة تستخدم Polling كل 10 ثواني لجلب الرسائل الجديدة

## إعداد Meta Developer Console

### الخطوة 1: الانتقال إلى تطبيقك
1. اذهب إلى [Meta for Developers](https://developers.facebook.com)
2. اختر تطبيقك

### الخطوة 2: إعداد Webhooks

#### لـ Facebook Messenger:
1. انتقل إلى **Messenger** > **Settings**
2. في قسم **Webhooks**, انقر **Add Callback URL**
3. أدخل:
   - **Callback URL**: `https://rihla.najmattalraafiden.com/webhooks/meta`
   - **Verify Token**: `rihla_webhook_verify_token_2024`
4. انقر **Verify and Save**
5. اشترك في الأحداث التالية:
   - `messages`
   - `messaging_postbacks`
   - `message_deliveries`
   - `message_reads`
   - `message_reactions`

#### لـ Instagram:
1. انتقل إلى **Instagram** > **Settings**
2. في قسم **Webhooks**, أضف نفس الـ Callback URL
3. اشترك في:
   - `messages`
   - `message_reactions`

### الخطوة 3: ربط الصفحات
1. انتقل إلى **Messenger** > **Settings** > **Access Tokens**
2. أضف الصفحات التي تريد استقبال رسائلها
3. اضغط **Generate Token** لكل صفحة

## التحقق من العمل

### اختبار Webhook:
```bash
curl "https://rihla.najmattalraafiden.com/webhooks/meta?hub.mode=subscribe&hub.verify_token=rihla_webhook_verify_token_2024&hub.challenge=test123"
```
يجب أن يعيد: `test123`

### اختبار الرسائل:
1. سجل دخول كعميل
2. اذهب إلى **صندوق الرسائل**
3. اضغط **مزامنة الرسائل** لجلب المحادثات الموجودة
4. أرسل رسالة تجريبية من Facebook/Instagram

## الملفات المهمة

| الملف | الوصف |
|-------|-------|
| `app/Http/Controllers/Webhooks/MetaWebhookController.php` | معالجة Webhooks |
| `app/Http/Controllers/Customer/MessagesController.php` | إدارة الرسائل |
| `app/Events/NewMessageReceived.php` | حدث الرسالة الجديدة |
| `app/Events/ConversationUpdated.php` | حدث تحديث المحادثة |
| `app/Models/Conversation.php` | نموذج المحادثة |
| `app/Models/Message.php` | نموذج الرسالة |
| `resources/views/customer/inbox/` | واجهة صندوق الرسائل |

## متغيرات البيئة

```env
# Meta Webhooks
META_WEBHOOK_VERIFY_TOKEN=rihla_webhook_verify_token_2024
```

## استكشاف الأخطاء

### الرسائل لا تصل؟
1. تحقق من سجلات Laravel: `storage/logs/laravel.log`
2. تأكد من تفعيل الـ Webhook في Meta Developer Console
3. تأكد من أن الصفحات مرتبطة بالتطبيق

### لا توجد محادثات؟
1. اضغط **مزامنة الرسائل** في صندوق الرسائل
2. تحقق من أن حسابات التواصل مرتبطة
3. تأكد من وجود محادثات في الصفحة/الحساب

## الأوامر المفيدة

```bash
# مسح الكاش
php artisan config:clear
php artisan cache:clear

# إعادة بناء الـ Assets
npm run build

# تشغيل الطوابير
php artisan queue:work
```
