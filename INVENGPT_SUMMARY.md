# InvenGPT API v3 - Integration Summary

## ✅ What Was Updated

### 1. New Service: InvenGptService
**File:** `app/Services/InvenGptService.php`

A dedicated service to communicate with InvenGPT API v3:
- `createSession()` - Create chat session for each lead
- `sendMessage()` - Send message and get AI reply
- `getOrCreateSession()` - Get cached session or create new one
- `endSession()` - End chat session
- `health()` - Check API health
- `webhook()` - Auto-creates session for messaging platforms

**Key Features:**
- Session management (1 lead = 1 session, 24-hour expiry)
- Laravel caching for session IDs
- Real-time product/lead data integration
- Human escalation detection
- Intent classification
- SQLite caching for common Q&A

---

### 2. Updated: AiChatService
**File:** `app/Services/AiChatService.php`

**Changes:**
- Now uses `InvenGptService` instead of direct Flask API calls
- Removed `callAiApi()` method (replaced with InvenGPT session-based approach)
- Updated `processMessage()` to use sessions
- Added human agent notification system
- Updated `testConnection()` to use InvenGPT health check
- Enhanced `updateConversationContext()` to store intent metadata

**New Flow:**
```
User Message → Get/Create Session → Send to InvenGPT → Get Reply + Metadata → Process Intent → Update Context
```

---

### 3. Configuration Updates

**File:** `config/services.php`
```php
'invengpt' => [
    'url' => env('INVENGPT_API_URL', 'http://127.0.0.1:5000'),
    'timeout' => env('INVENGPT_TIMEOUT', 60),
    'laravel_api_url' => env('APP_URL', 'http://127.0.0.1:8001'),
],
```

**File:** `.env.example`
```env
INVENGPT_API_URL=http://127.0.0.1:5000
INVENGPT_TIMEOUT=60
```

---

### 4. UI Improvements

**File:** `resources/views/customer/ai-settings/index.blade.php`

**Added:**
- Info banner explaining InvenGPT API v3 features
- Updated label: "رابط API الذكاء الاصطناعي" → "رابط InvenGPT API"
- Added example URL hint
- New CSS for `.alert-info` style

**Features Highlighted:**
- Session management per customer
- Cache for common questions
- Auto escalation to human agent
- Full Iraqi Arabic support

---

### 5. API Endpoints (Already Implemented)

These Laravel APIs are now used by InvenGPT:

```http
GET /api/v1/leads/{id}        # Customer details
GET /api/v1/products/{id}     # Product details
GET /api/v1/stores/{id}       # Store details
GET /api/v1/products          # List products
GET /api/v1/leads             # List customers (by store)
```

All endpoints:
- No authentication required (public API)
- CORS enabled for all domains
- Return JSON with `success` flag

---

## 🔄 Migration from Old System

### Before (Old Flask API)
```php
$response = Http::post($apiUrl . '/chat', [
    'message' => $message,
    'context' => $fullContext,  // Sent every time
    'conversation_history' => $history,  // Sent every time
    'max_tokens' => 150,
    'temperature' => 0.7,
]);

$reply = $response->json()['response'] ?? null;
```

### After (InvenGPT API v3)
```php
// One-time session creation
$sessionId = $invengpt->getOrCreateSession($storeId, $leadId);

// Send message (context stored in session)
$result = $invengpt->sendMessage($sessionId, $message);
$reply = $result['reply'];
$actionRequired = $result['action_required']; // 'human_agent_needed' or null
$intent = $result['_metadata']['intent']; // 'greeting', 'order', etc.
```

**Benefits:**
- ✅ No need to send full context every time
- ✅ Automatic caching for common Q&A
- ✅ Intent detection built-in
- ✅ Human escalation detection
- ✅ Faster responses (cache hit < 1 second)

---

## 📊 How Session Management Works

### Session Lifecycle

1. **First Message** (Lead sends "مرحبا")
   ```
   Laravel → InvenGPT: POST /api/v3/session/create
   InvenGPT: Creates session + fetches lead from Laravel API
   InvenGPT → Laravel: Returns session_id
   Laravel: Caches session_id for 24 hours
   ```

2. **Subsequent Messages**
   ```
   Laravel: Gets session_id from cache
   Laravel → InvenGPT: POST /api/v3/chat with session_id
   InvenGPT: Uses stored context + conversation history
   InvenGPT → Laravel: Returns reply + intent + metadata
   ```

3. **Session Expiry** (after 24 hours)
   ```
   Laravel: session_id not in cache
   Laravel → InvenGPT: Creates new session
   (Cycle repeats)
   ```

### Cache Strategy

**Laravel Cache (Redis/Database):**
```
Key: "invengpt_session_{store_id}_{lead_id}"
Value: "uuid-session-id"
TTL: 24 hours
```

**InvenGPT Cache (SQLite):**
- Common questions cached per store
- Examples: "شنو أوقات العمل؟", "شكد التوصيل؟"
- Instant responses (no AI generation needed)

---

## 🎯 Intent Detection

InvenGPT automatically classifies every message:

| Intent | Arabic Example | Action |
|--------|---------------|--------|
| `greeting` | "مرحبا", "هلو" | Friendly welcome |
| `product_inquiry` | "شنو عندكم؟" | Show products |
| `price_inquiry` | "شكد السعر؟" | Show prices |
| `availability_check` | "موجود؟" | Check stock |
| `order` | "اريد اطلب" | Start order flow |
| `delivery_info` | "شكد التوصيل؟" | Show delivery info |
| `complaint` | "جابولي غلط" | Escalate to human |
| `needs_human` | Complex query | Escalate to human |

**Stored in conversation context:**
```php
$conversation->ai_context['last_intent'] = 'order';
$conversation->ai_context['exchanges'][0]['intent'] = 'greeting';
```

---

## 🚨 Human Escalation

When `action_required === 'human_agent_needed'`:

1. AI still sends reply to customer
2. Laravel creates notification for merchant
3. Merchant sees alert in dashboard
4. Merchant can take over conversation

**Triggers:**
- Complaints
- Price negotiations
- Complex questions AI can't answer
- Order modifications

**Implementation:**
```php
if ($actionRequired === 'human_agent_needed') {
    Notification::create([
        'user_id' => $this->user->id,
        'type' => 'human_agent_needed',
        'title' => 'تحتاج محادثة إلى تدخل بشري',
        'message' => "المحادثة مع {$lead->name} تحتاج إلى موظف",
        'data' => ['conversation_id' => $conversation->id],
    ]);
}
```

---

## 📁 New Files

1. **`app/Services/InvenGptService.php`** - InvenGPT integration service
2. **`INVENGPT_INTEGRATION.md`** - Detailed integration guide
3. **`INVENGPT_SUMMARY.md`** - This file

---

## 🔧 Configuration Required

### 1. Add to `.env`

```env
INVENGPT_API_URL=http://127.0.0.1:5000
INVENGPT_TIMEOUT=60
APP_URL=http://127.0.0.1:8001
```

### 2. Start InvenGPT Server

```bash
cd /path/to/invengpt
python app.py
```

Verify health:
```bash
curl http://127.0.0.1:5000/health
```

Expected:
```json
{
    "status": "healthy",
    "model_loaded": true
}
```

### 3. Test from Laravel UI

1. Login as merchant
2. Go to `/customer/ai-settings`
3. Click "اختبار" button
4. Should show: "✓ الاتصال ناجح - InvenGPT API v3"

---

## 🐛 Debugging

### Check Session Cache
```php
use Illuminate\Support\Facades\Cache;

$sessionId = Cache::get("invengpt_session_1_4");
dd($sessionId); // Should show UUID or null
```

### Check Logs
```bash
tail -f storage/logs/laravel.log | grep InvenGPT
```

Look for:
- `InvenGPT: Session created`
- `InvenGPT: Message sent`
- `InvenGPT Response Metadata`

### Check Conversation Context
```php
$conversation = Conversation::find(1);
dd($conversation->ai_context);

// Should contain:
// - exchanges: Message history with intents
// - collected_data: Customer info (name, phone, etc.)
// - last_intent: Last detected intent
// - ai_conversation_context: InvenGPT internal context
```

---

## 📈 Performance

### Response Times

| Scenario | Time | Cached? |
|----------|------|---------|
| First message (session creation) | 5-10s | No |
| Cached Q&A ("شنو أوقات العمل؟") | <1s | Yes |
| Regular AI generation | 2-5s | No |
| Second time same question | <1s | Yes |

### Optimization Tips

1. **Keep product list small** - Max 20 products per session
2. **Use store policies** - Common Q&A gets cached
3. **Monitor cache hits** - Check `cached: true` in responses
4. **Set proper timeout** - 60s for AI generation

---

## ✅ Testing Checklist

- [ ] InvenGPT server is running (`curl /health`)
- [ ] Laravel .env has `INVENGPT_API_URL`
- [ ] AI Settings UI shows "اتصال ناجح"
- [ ] Send test message from customer
- [ ] Check session created in logs
- [ ] Check reply received
- [ ] Check intent detected
- [ ] Test human escalation (send complaint)
- [ ] Check notification created

---

## 🎓 Quick Start Example

```php
use App\Services\InvenGptService;

$invengpt = new InvenGptService();

// 1. Health check
if (!$invengpt->health()) {
    die('InvenGPT server not running!');
}

// 2. Create session
$result = $invengpt->createSession(
    storeId: 1,
    leadId: 4
);

$sessionId = $result['session_id'];

// 3. Send message
$response = $invengpt->sendMessage($sessionId, 'مرحبا');

echo $response['reply']; // "هلا والله! كيف أقدر أخدمك؟"
echo $response['_metadata']['intent']; // "greeting"
echo $response['cached'] ? 'Cached' : 'Generated'; // "Generated"

// 4. Send same message again
$response2 = $invengpt->sendMessage($sessionId, 'مرحبا');
echo $response2['cached'] ? 'Cached' : 'Generated'; // "Cached" (instant!)
```

---

## 📞 Support

**InvenGPT API Issues:**
- Check `/health` endpoint
- Review InvenGPT server logs
- Verify model is loaded

**Laravel Integration Issues:**
- Check `storage/logs/laravel.log`
- Test API endpoints with Postman
- Verify session cache is working

**Performance Issues:**
- Monitor response times
- Check cache hit rate
- Reduce product count if needed
- Increase timeout if AI is slow
