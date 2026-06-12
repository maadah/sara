# Native Groq AI Integration for Rehla-AI

## Overview

This document describes the native Laravel integration with Groq AI, replacing the previous Python Flask API (InvenGPT) with direct Groq API calls.

## Key Features

### 1. **No Python Server Required**
- Direct integration with Groq API from Laravel
- All AI logic now runs within Laravel
- Sessions stored in database (not memory)

### 2. **Fast Replies (No API Call)**
Simple greetings and thanks are handled locally:
- `السلام عليكم` → Random greeting from database
- `شكرا` → Random thanks reply from database
- Response time: < 10ms

### 3. **Multi-Item Cart with Quantity Parsing**
```
"اريد بنطرونين" → Detects dual form = 2 items
"ثلاث قمصان" → Detects number word = 3 items
"4 تشيرت" → Detects digit = 4 items
```

### 4. **Sequential Customer Info Collection**
The AI asks for info ONE AT A TIME:
1. First: Name (`شنو اسمك؟`)
2. Then: Phone (`شنو رقم تلفونك؟`)
3. Finally: Address (`وين نوصل إلك؟`)

### 5. **Order Confirmation with Items**
When all info is complete, order is created with:
- Multiple items (from cart)
- Correct quantities
- Proper pricing
- Stock deduction

## Database Tables

### `ai_chat_sessions`
Stores chat sessions per lead:
- `id` (UUID)
- `user_id` (store owner)
- `lead_id` (customer)
- `store_context` (products, store info)
- `messages` (chat history)
- `cart` (current items)
- `customer_data` (name, phone, address)
- `expires_at` (30 minutes)

### `ai_fast_replies`
Customizable quick responses:
- `category` (greetings, thanks_reply, ask_name, etc.)
- `reply` (Arabic text)
- `trigger_keywords` (when to use)

## Configuration

### Environment Variables
```env
GROQ_API_KEY=gsk_xxxxxxxxxxxx
GROQ_MODEL=llama-3.3-70b-versatile
```

### AI Settings (per store)
In `ai_settings` table:
- `groq_api_key` - API key (can be per-store)
- `groq_model` - Model selection
- `session_timeout_minutes` - Default 30
- `enable_upsell` - Suggest related products
- `enable_fast_replies` - Use local replies

## Services

### `GroqChatService`
Main AI service:
```php
$service = new GroqChatService($user);
$result = $service->processMessage($conversation, $lead, $message);

// Returns:
[
    'reply' => 'AI response text',
    'intent' => 'order_confirmed',
    'cart' => [...items...],
    'customer_data' => ['name' => '...', 'phone' => '...'],
    'current_order' => [...order data...],
]
```

### `AiChatService`
Orchestrates the flow:
1. Calls GroqChatService
2. Updates lead with customer data
3. Creates orders when confirmed
4. Updates conversation context

## Order Creation Flow

```
Customer: "اريد بنطرونين"
AI: "📦 سلة الطلب:
     - بنطرون × 2 = 20000 دينار
     💰 المجموع: 20000 دينار
     شنو اسمك الكريم؟"

Customer: "زيد اسامه"
AI: "📦 سلة الطلب:
     - بنطرون × 2 = 20000 دينار
     💰 المجموع: 20000 دينار
     زين يا زيد، شنو رقم تلفونك؟"

Customer: "07832563259"
AI: "📦 سلة الطلب:
     - بنطرون × 2 = 20000 دينار
     💰 المجموع: 20000 دينار
     ممتاز! وين نوصل إلك؟"

Customer: "بغداد حي الجهاد"
AI: "طلبك مؤكد! 🎉
     📦 المنتجات:
     بنطرون × 2 = 20000 دينار
     💰 المجموع: 20000 دينار
     📋 بياناتك:
     👤 الاسم: زيد اسامه
     📱 التلفون: 07832563259
     📍 العنوان: بغداد حي الجهاد
     سنتواصل معك قريباً! 🙏"

→ Order created in database with 2 items
→ Stock decremented by 2
→ Notification sent to store owner
```

## Fixes Applied

1. **Quantity Parsing**: Fixed dual form detection (بنطرونين = 2, not 4)
2. **Customer Info**: Now asks one field at a time
3. **Order Items**: Uses `unit_price` column (was `price`)
4. **Name Extraction**: Doesn't confuse city names with customer names
5. **Address Detection**: Handles "بغداد - حي الجهاد" format

## Models Available

| Model | Description |
|-------|-------------|
| llama-3.3-70b-versatile | Best for complex conversations |
| llama-3.1-70b-versatile | Stable and powerful |
| llama-3.1-8b-instant | Fast for simple replies |
| mixtral-8x7b-32768 | Good balance |
| gemma2-9b-it | Lightweight |

## Testing

1. Go to AI Settings page
2. Enter Groq API key
3. Enable AI and auto-reply
4. Test in inbox conversation

Get your API key from: https://console.groq.com/keys
