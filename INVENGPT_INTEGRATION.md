# InvenGPT API v3 - Integration Guide

## Overview

This Laravel application is now integrated with **InvenGPT API v3**, a production-ready AI chatbot system with:

- ✅ **Session Management** - Each lead (customer) has their own chat session
- ✅ **SQLite Caching** - Common Q&A responses are cached for instant replies
- ✅ **Human Escalation** - Automatically detects when human intervention is needed
- ✅ **Real-time Data** - AI fetches product and lead info from Laravel APIs

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# InvenGPT AI Service
INVENGPT_API_URL=http://127.0.0.1:5000
INVENGPT_TIMEOUT=60

# Application URL (for AI to call back)
APP_URL=http://127.0.0.1:8001
```

### AI Settings UI

Merchants can configure AI settings at:
```
http://your-app/customer/ai-settings
```

Settings include:
- API URL (InvenGPT server)
- Enable/disable AI
- Enable/disable auto-reply
- Store description & policies
- Greeting message

---

## How It Works

### 1. Session Creation

When a customer sends their first message:

```php
// In AiChatService::processMessage()
$sessionId = $this->invengpt->getOrCreateSession($this->user->id, $lead->id);
```

This creates a session in InvenGPT that:
- Lasts 24 hours (1440 minutes)
- Stores conversation history
- Remembers customer context
- Is cached in Laravel for quick access

### 2. Message Processing

```php
$result = $this->invengpt->sendMessage($sessionId, $message);

// Result contains:
// - reply: The AI response text
// - action_required: 'human_agent_needed' or null
// - cached: Whether response was from cache
// - _metadata: Intent, thought process, entities
```

### 3. Intent Detection

InvenGPT detects these intents automatically:

| Intent | Example Message |
|--------|----------------|
| `greeting` | "مرحبا", "هلو" |
| `product_inquiry` | "شنو عندكم؟" |
| `price_inquiry` | "شكد السعر؟" |
| `availability_check` | "موجود؟" |
| `order` | "اريد اطلب" |
| `delivery_info` | "شكد التوصيل؟" |
| `complaint` | "جابولي غلط" |
| `needs_human` | Complex queries |

### 4. Human Escalation

When `action_required === 'human_agent_needed'`:

```php
if ($actionRequired === 'human_agent_needed') {
    $this->notifyHumanAgent($conversation, $lead, $message);
}
```

A notification is created for the merchant to take over.

---

## API Endpoints

### Laravel Provides (for AI to fetch data)

These are already implemented in `routes/api.php`:

#### Get Lead Details
```http
GET /api/v1/leads/{lead_id}
```

Returns customer info: name, phone, city, order history, etc.

#### Get Product Details
```http
GET /api/v1/products/{product_id}
```

Returns product info: name, price, stock, images, category.

#### Get Store Details
```http
GET /api/v1/stores/{store_id}
```

Returns store info: name, policies, working hours, etc.

---

## Services

### InvenGptService

Located: `app/Services/InvenGptService.php`

Main methods:

```php
// Create new session
$result = $invengpt->createSession($storeId, $leadId);
// Returns: ['session_id' => string, 'expires_in_minutes' => int, 'is_existing' => bool]

// Send message
$result = $invengpt->sendMessage($sessionId, $message);
// Returns: ['reply' => string, 'action_required' => string|null, 'cached' => bool, '_metadata' => array]

// Get or create session (uses cache)
$sessionId = $invengpt->getOrCreateSession($storeId, $leadId);

// End session
$success = $invengpt->endSession($sessionId);

// Health check
$isHealthy = $invengpt->health();

// Webhook (auto-creates session)
$result = $invengpt->webhook($storeId, $leadId, $message, 'facebook');
```

### AiChatService

Located: `app/Services/AiChatService.php`

Main method:

```php
// Process incoming message and generate AI response
$response = $aiService->processMessage($conversation, $message);
```

This handles:
- Session management
- Customer info extraction
- Product interest detection
- Order creation (if conditions met)
- Context updates
- Human escalation

---

## Conversation Flow

### Example: Customer Orders a Product

1. **Customer**: "مرحبا"
   - AI: "هلا والله! كيف أقدر أخدمك؟"
   - Intent: `greeting`

2. **Customer**: "شنو عندكم تيشرتات؟"
   - AI: "عندنا تيشرت قطني بـ25,000 دينار..."
   - Intent: `product_inquiry`

3. **Customer**: "اريد تيشرت"
   - AI: "حلو! شنو اسمك؟"
   - Intent: `order`
   - Starts collecting customer info

4. **Customer**: "اسمي علي"
   - AI: "تمام علي! شنو رقم تلفونك؟"
   - Extracted: name = "علي"

5. **Customer**: "07712345678"
   - AI: "زين! وين عنوانك بالضبط؟"
   - Extracted: phone = "07712345678"

6. **Customer**: "بغداد - الكرادة"
   - AI: "تمام! راح نجهز طلبك..."
   - Extracted: city = "بغداد", address = "الكرادة"
   - **Order Created** automatically

---

## Caching Strategy

### Session Caching (Laravel)

```php
Cache::put("invengpt_session_{$storeId}_{$leadId}", $sessionId, now()->addDay());
```

- Cached for 24 hours
- Avoids creating duplicate sessions
- Fast session retrieval

### Response Caching (InvenGPT)

InvenGPT caches common Q&A:
- "شنو اوقات العمل؟" → Same answer every time
- "شكد التوصيل؟" → Same answer every time
- Reduces AI generation time
- Per-store cache (different stores, different answers)

---

## Testing

### Health Check

```bash
curl http://127.0.0.1:5000/health
```

Expected response:
```json
{
    "status": "healthy",
    "model_loaded": true,
    "device": "cuda",
    "timestamp": "2025-12-10T18:00:00.000000"
}
```

### Test Connection from UI

1. Go to `/customer/ai-settings`
2. Click "اختبار" button next to API URL
3. Should show: "✓ الاتصال ناجح - InvenGPT API v3"

### Manual Message Test

```bash
# 1. Create session
curl -X POST http://127.0.0.1:5000/api/v3/session/create \
  -H "Content-Type: application/json" \
  -d '{
    "store_id": "1",
    "lead_id": "1",
    "store_context": {
      "name": "Test Store",
      "products": [{"id": "1", "name": "Shirt", "price": 25000, "stock": 10}]
    },
    "lead_info": {
      "name": "Test Customer",
      "phone": null,
      "city": null,
      "status": "new"
    }
  }'

# 2. Send message
curl -X POST http://127.0.0.1:5000/api/v3/chat \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "YOUR_SESSION_ID_FROM_ABOVE",
    "message": "مرحبا"
  }'
```

---

## Troubleshooting

### "Connection refused"

- InvenGPT server is not running
- Check: `curl http://127.0.0.1:5000/health`
- Start server: `python app.py` (in InvenGPT directory)

### "Session not found"

- Session expired (24 hours)
- Laravel will auto-create new session

### "Human agent needed" appears often

- Complex queries AI can't handle
- Check conversation context in `conversations.ai_context`
- Review AI logs in `storage/logs/laravel.log`

### Slow responses

- First message in session: 5-10 seconds (AI generation)
- Cached responses: <1 second
- Check `cached: true` in response metadata

---

## Monitoring

### Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep InvenGPT
```

Key log entries:
- `InvenGPT: Session created`
- `InvenGPT: Message sent`
- `InvenGPT Response Metadata`
- `Human agent notification sent`

### Check Session Cache

```php
use Illuminate\Support\Facades\Cache;

$sessionId = Cache::get("invengpt_session_{$storeId}_{$leadId}");
```

### Check Conversation Context

```php
$conversation = Conversation::find($id);
$context = $conversation->ai_context;

// Check:
// - exchanges: Message history
// - collected_data: Customer info
// - last_intent: Last detected intent
// - ai_conversation_context: InvenGPT context
```

---

## Customization

### Adjust Session Timeout

In InvenGPT server (not Laravel):
```python
# Default: 1440 minutes (24 hours)
# Change in InvenGPT config
```

### Modify Store Context

In `InvenGptService::createSession()`:

```php
$storeContext = [
    'name' => $store->name,
    'working_hours' => '9am - 10pm',  // Customize
    'delivery_time' => 'Same day',     // Customize
    'delivery_cost' => 5000,           // Customize
    'return_policy' => '3 days',       // Customize
    'products' => $products,
];
```

### Add Custom Intents

InvenGPT handles this - contact AI team to train new intents.

---

## Migration from Old System

### Old System (Flask API)
```php
// Old method
$response = Http::post($apiUrl . '/chat', [
    'message' => $message,
    'context' => $context,
    'conversation_history' => $history,
]);
```

### New System (InvenGPT v3)
```php
// New method
$sessionId = $invengpt->getOrCreateSession($storeId, $leadId);
$result = $invengpt->sendMessage($sessionId, $message);
$response = $result['reply'];
```

**Key Differences:**
1. ✅ Session-based (no need to send full context every time)
2. ✅ Automatic caching
3. ✅ Human escalation detection
4. ✅ Intent classification
5. ✅ Real-time data fetching from Laravel

---

## Production Checklist

- [ ] Set `INVENGPT_API_URL` to production server
- [ ] Set `APP_URL` to production Laravel URL
- [ ] Ensure InvenGPT server is running
- [ ] Test `/health` endpoint
- [ ] Test session creation
- [ ] Test message sending
- [ ] Verify human escalation notifications
- [ ] Monitor logs for errors
- [ ] Set up proper timeout values
- [ ] Configure CORS in `config/cors.php` if needed

---

## Support

For InvenGPT API issues:
- Check InvenGPT server logs
- Verify `/health` endpoint returns `model_loaded: true`
- Review Laravel logs for HTTP errors

For Laravel integration issues:
- Check `storage/logs/laravel.log`
- Verify API endpoints return valid data
- Test with Postman/cURL directly
