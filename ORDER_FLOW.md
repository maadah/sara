# AI Order Flow Documentation

## Overview
The AI chat system now has a **smart order flow** that remembers customer information and requires explicit confirmation before creating orders.

## Flow Steps

### 1. **Customer Orders Products**
```
Customer: "اريد قميصين وبنطرون"
AI: Shows cart with 2 shirts + 1 pants
```

### 2. **Sequential Information Collection**
The AI asks for ONE piece of information at a time:

#### Step A: Ask for Name (if missing)
```
🛒 سلة الطلب:
• قميص × 2 = 30000 دينار
• بنطرون × 1 = 10000 دينار
💰 المجموع: 40000 دينار

شنو اسمك الكريم؟
```

#### Step B: Ask for Phone (if missing)
```
🛒 سلة الطلب:
• قميص × 2 = 30000 دينار
• بنطرون × 1 = 10000 دينار
💰 المجموع: 40000 دينار

زين يا احمد، شنو رقم تلفونك؟
```

#### Step C: Ask for Address (if missing)
```
🛒 سلة الطلب:
• قميص × 2 = 30000 دينار
• بنطرون × 1 = 10000 دينار
💰 المجموع: 40000 دينار

ممتاز! وين نوصل إلك؟
```

### 3. **Order Confirmation (NOT auto-creation)**
After collecting all info, AI shows summary and **asks for confirmation**:

```
📋 ملخص الطلب:

📦 المنتجات:
• قميص × 2 = 30000 دينار
• بنطرون × 1 = 10000 دينار

💰 المجموع: 40000 دينار

📋 بياناتك:
👤 الاسم: احمد
📱 التلفون: 07832563259
📍 العنوان: البصرة - ابي الخصيب

✅ تأكيد الطلب؟ (نعم/لا)
```

### 4. **Customer Confirms or Cancels**

#### If Confirmed (نعم/اي/تمام/ok/yes):
```
✅ طلبك مؤكد! 🎉

📦 المنتجات:
• قميص × 2 = 30000 دينار
• بنطرون × 1 = 10000 دينار

💰 المجموع: 40000 دينار

📋 بياناتك:
👤 الاسم: احمد
📱 التلفون: 07832563259
📍 العنوان: البصرة - ابي الخصيب

سنتواصل معك قريباً لتأكيد الطلب. شكراً! 🙏
```
→ Order created in database, cart cleared, customer info **saved for next time**

#### If Cancelled (لا/كلا/الغي/no):
```
تمام، تم إلغاء الطلب ✅
إذا تحتاج شي، خبرني! 😊
```
→ Cart cleared, no order created

### 5. **Future Orders - Smart Info Retention**
When the same customer orders again:

```
Customer: "اريد تشيرت"
AI: Shows cart, then checks what info is missing
```

**If name/phone/address already saved:**
```
🛒 سلة الطلب:
• تشيرت × 1 = 20000 دينار
💰 المجموع: 20000 دينار

📋 ملخص الطلب:
📦 المنتجات:
• تشيرت × 1 = 20000 دينار

💰 المجموع: 20000 دينار

📋 بياناتك:
👤 الاسم: احمد
📱 التلفون: 07832563259
📍 العنوان: البصرة - ابي الخصيب

✅ تأكيد الطلب؟ (نعم/لا)
```
→ No need to ask for info again!

**If customer wants to change info:**
```
Customer: "اسمي محمد"
AI: Updates name, shows new summary
```

## Intent Keywords

### Order Reference ("Already Ordered")
Keywords: `طلبت`, `طلبي`, `الطلب`, `سابقا`, `سابقآ`, `قبل`

```
Customer: "طلبت سابقآ"
AI: Checks if there's a pending cart
  - If yes: Shows cart and asks for missing info
  - If no: "شنو تحب تطلب هالمره؟" + product list
```

### Confirmation
Keywords: `نعم`, `اي`, `صح`, `تمام`, `اكيد`, `موافق`, `اوكي`, `ok`, `yes`

### Cancellation
Keywords: `لا`, `كلا`, `لاء`, `ما اريد`, `ماريد`, `الغي`, `no`

## Cart Behavior

### Cart Persistence
- Cart stays **open** until customer confirms or cancels
- Cart is **NOT cleared** after collecting address
- Customer can modify cart at any time before confirmation

### Quantity Replacement (Not Accumulation)
```
Customer: "اريد قميص"        → Cart: 1 shirt
Customer: "قميص"             → Cart: 1 shirt (NOT 2)
Customer: "اريد قميصين"      → Cart: 2 shirts (replaced)
```

### Stock Validation
Before asking for customer info, AI checks stock:
```
⚠️ بعض المنتجات غير متوفرة بالكمية المطلوبة:
❌ قميص: المطلوب 10، المتوفر 5

تريد أعدّل الكميات للمتوفر أو أحذفها؟
```

## Session Management

### Session Lifetime
- Sessions expire after **30 minutes** of inactivity
- Each message extends session lifetime
- Session stores: cart, customer_data, messages, store_context

### Customer Data Storage
Customer info is saved in:
1. **AiChatSession** (`customer_data` JSON column) - temporary
2. **Lead** model (name, phone, address) - permanent

This allows the system to:
- Pre-fill info for returning customers
- Track customer history
- Avoid asking for same info repeatedly

## Order States

| State | Description | Cart Status | Next Step |
|-------|-------------|-------------|-----------|
| `asking_name` | Waiting for customer name | Open | Ask phone |
| `asking_phone` | Waiting for phone number | Open | Ask address |
| `asking_address` | Waiting for address | Open | Show confirmation |
| `asking_confirmation` | Waiting for yes/no | Open | Create or cancel |
| `order_confirmed` | Order created successfully | **Cleared** | Ready for new order |
| `order_cancelled` | Customer cancelled | **Cleared** | Ready for new order |

## Benefits

✅ **No accidental orders** - requires explicit confirmation  
✅ **Remembers customer info** - faster repeat orders  
✅ **Clear order summary** - customer reviews before confirming  
✅ **Flexible modifications** - can change info before confirming  
✅ **Smart cart management** - persists until decision made  
✅ **Professional flow** - feels like human conversation  

## Testing Scenarios

### Scenario 1: New Customer
1. "اريد قميص" → Cart shown, asks for name
2. "احمد" → Asks for phone
3. "07832563259" → Asks for address
4. "بغداد - الكرادة" → Shows summary, asks confirmation
5. "نعم" → Order created ✅

### Scenario 2: Returning Customer
1. "اريد بنطرون" → Cart shown, shows summary immediately (info saved)
2. "نعم" → Order created ✅

### Scenario 3: Customer Changes Mind
1. "اريد قميص" → Cart shown
2. Provide all info
3. Summary shown, asks confirmation
4. "لا" → Cart cleared, no order ✅

### Scenario 4: Reference Previous Order
1. "طلبت سابقآ" → AI checks if cart exists
2. If cart exists → shows cart and continues
3. If no cart → "شنو تحب تطلب هالمره؟"

## Database Tables

### `ai_chat_sessions`
Stores temporary session data:
- `cart` (JSON): Current cart items
- `customer_data` (JSON): Name, phone, address
- `messages` (JSON): Conversation history
- `current_order` (JSON): Pending order before confirmation

### `leads`
Stores permanent customer data:
- `name`: Customer name
- `phone`: Phone number
- `address`: Delivery address
- Updated when customer provides info

### `online_orders`
Created only after customer confirms:
- Links to lead, conversation
- Stores customer info (name, phone, address)
- Status: 'pending'
