# إعداد Meta Developer لتسجيل الدخول عبر Facebook/Instagram

## الخطوة 1: إنشاء حساب مطور على Meta

1. اذهب إلى [Meta for Developers](https://developers.facebook.com/)
2. سجّل الدخول بحسابك على Facebook
3. إذا كانت أول مرة، اضغط على "Get Started" واتبع الخطوات لإنشاء حساب مطور

## الخطوة 2: إنشاء تطبيق جديد

1. من لوحة التحكم، اضغط على **"Create App"** أو **"My Apps" > "Create App"**
2. اختر نوع التطبيق:
   - اختر **"Consumer"** أو **"Business"** (للأعمال التجارية)
3. أدخل معلومات التطبيق:
   - **App Name**: `رحلة` أو اسم منصتك
   - **App Contact Email**: بريدك الإلكتروني
4. اضغط **"Create App"**

## الخطوة 3: إضافة Facebook Login

1. في لوحة تحكم التطبيق، ابحث عن **"Facebook Login"** واضغط **"Set Up"**
2. اختر **"Web"**
3. أدخل رابط موقعك:
   ```
   https://rihla.najmattalraafiden.com
   ```
4. اضغط **"Save"** ثم **"Continue"**

## الخطوة 4: تكوين إعدادات Facebook Login

1. من القائمة الجانبية، اذهب إلى **"Facebook Login" > "Settings"**
2. أضف الروابط التالية في **"Valid OAuth Redirect URIs"**:
   ```
   https://rihla.najmattalraafiden.com/auth/facebook/callback
   ```
3. فعّل الخيارات التالية:
   - ✅ Client OAuth Login
   - ✅ Web OAuth Login
   - ✅ Enforce HTTPS
4. اضغط **"Save Changes"**

## الخطوة 5: إعداد Instagram Basic Display (اختياري)

إذا أردت الوصول لحسابات Instagram:

1. من لوحة التحكم، أضف منتج **"Instagram Basic Display"**
2. أدخل الروابط:
   - **Valid OAuth Redirect URIs**: `https://rihla.najmattalraafiden.com/auth/instagram/callback`
   - **Deauthorize callback URL**: `https://rihla.najmattalraafiden.com/auth/instagram/deauthorize`
   - **Data Deletion Request URL**: `https://rihla.najmattalraafiden.com/auth/instagram/delete`

## الخطوة 6: الحصول على بيانات التطبيق

1. اذهب إلى **"Settings" > "Basic"**
2. ستجد:
   - **App ID**: هذا هو `FACEBOOK_CLIENT_ID`
   - **App Secret**: اضغط "Show" للحصول على `FACEBOOK_CLIENT_SECRET`

## الخطوة 7: إضافة الصلاحيات (Permissions)

1. اذهب إلى **"App Review" > "Permissions and Features"**
2. اطلب الصلاحيات التالية:
   - `email` ✅ (متاح افتراضياً)
   - `public_profile` ✅ (متاح افتراضياً)
   - `pages_show_list` (لعرض صفحات Facebook)
   - `pages_read_engagement` (لقراءة التفاعلات)
   - `pages_manage_posts` (لنشر منشورات)
   - `instagram_basic` (للوصول لـ Instagram)
   - `instagram_content_publish` (لنشر على Instagram)
   - `instagram_manage_comments` (لإدارة التعليقات)

> ⚠️ **ملاحظة**: بعض الصلاحيات تتطلب مراجعة من Meta قبل استخدامها في الإنتاج

## الخطوة 8: تحديث ملف .env

أضف المتغيرات التالية في ملف `.env`:

```env
# Facebook/Meta OAuth
FACEBOOK_CLIENT_ID=YOUR_APP_ID_HERE
FACEBOOK_CLIENT_SECRET=YOUR_APP_SECRET_HERE
FACEBOOK_REDIRECT_URI=https://rihla.najmattalraafiden.com/auth/facebook/callback

# Instagram OAuth (يستخدم نفس بيانات Facebook)
INSTAGRAM_REDIRECT_URI=https://rihla.najmattalraafiden.com/auth/instagram/callback
```

## الخطوة 9: تفعيل التطبيق للاستخدام العام

1. اذهب إلى **"Settings" > "Basic"**
2. أكمل جميع البيانات المطلوبة:
   - Privacy Policy URL
   - Terms of Service URL (اختياري)
   - App Icon
3. في أعلى الصفحة، غيّر الوضع من **"Development"** إلى **"Live"**

---

## ملاحظات مهمة

### وضع التطوير (Development Mode)
- في وضع التطوير، فقط مدراء التطبيق والمختبرين يمكنهم استخدام التطبيق
- لإضافة مختبرين: **"Roles" > "Test Users"** أو **"Roles" > "Testers"**

### وضع الإنتاج (Live Mode)
- يتطلب إكمال جميع البيانات المطلوبة
- بعض الصلاحيات تتطلب **App Review** من Meta
- تحتاج سياسة خصوصية على موقعك

### للاختبار المحلي
إذا كنت تعمل على localhost:
```env
FACEBOOK_REDIRECT_URI=http://localhost:8001/auth/facebook/callback
```
ثم أضف هذا الرابط في Valid OAuth Redirect URIs على Meta

---

## روابط مفيدة

- [Facebook Login Documentation](https://developers.facebook.com/docs/facebook-login/)
- [Instagram Basic Display API](https://developers.facebook.com/docs/instagram-basic-display-api/)
- [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
- [Access Token Debugger](https://developers.facebook.com/tools/debug/accesstoken/)

---

## مثال على ملف .env الكامل

```env
APP_NAME="رحلة"
APP_ENV=production
APP_URL=https://rihla.najmattalraafiden.com

# Facebook/Meta OAuth
FACEBOOK_CLIENT_ID=123456789012345
FACEBOOK_CLIENT_SECRET=abcdef1234567890abcdef1234567890
FACEBOOK_REDIRECT_URI=https://rihla.najmattalraafiden.com/auth/facebook/callback
INSTAGRAM_REDIRECT_URI=https://rihla.najmattalraafiden.com/auth/instagram/callback
```
