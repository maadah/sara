# GroqChatServiceV2 - Complete Rewrite Summary

## Overview
`GroqChatServiceV2.php` is a **complete rewrite** of the AI chat service with all bug fixes and comprehensive improvements for handling Iraqi Arabic e-commerce conversations.

## Key Bug Fixes from Original Issues

### 1. ✅ Cart Not Updating
- **Problem**: "اضفلي", "اريد قميصين" was not adding to cart
- **Fix**: Separate intents for `add_to_cart`, `update_quantity`, `remove_from_cart`

### 2. ✅ Quantity Changes Ignored  
- **Problem**: "سويهم 3", "الابيض 3" context was lost
- **Fix**: `last_mentioned_product` tracking + contextual quantity updates

### 3. ✅ Orders Not Saved to Database
- **Problem**: Order confirmation didn't save to database
- **Fix**: Guaranteed persistence in `handleConfirmation()` with verification

### 4. ✅ Order Status Lookup Broken
- **Problem**: Couldn't find existing orders
- **Fix**: Uses phone + lead_id + conversation_id to find orders

### 5. ✅ Product Variants Not Working
- **Problem**: "اكو غير نوعيه" didn't search alternatives  
- **Fix**: `handleProductVariant()` with smart search

### 6. ✅ Cancel Without Status Check
- **Problem**: Could cancel already-completed orders
- **Fix**: Status check before cancel (only pending/new can be cancelled)

---

## Comprehensive Arabic Number Support

### NUM_WORDS (0-50)
```php
- 0-10: صفر، واحد، واحده، اثنين، ثلاثه، اربعه، خمسه، سته، سبعه، ثمانيه، تسعه، عشره
- 11-19: احدعشر، اثناعشر، ثلاثعشر، اربعطعشر، خمسطعشر، ستطعشر، سبعطعشر، ثمانطعشر، تسعطعشر
- 20-50: عشرين، ثلاثين، اربعين، خمسين + واحد وعشرين، اثنين وعشرين
```

### DUAL_PATTERNS (Quantity = 2)
```php
- Clothing: قميصين، بنطرونين، تشيرتين، هوديين، فستانين، جاكيتين، بلوزتين، شورتين
- Accessories: حذائين، نظارتين، ساعتين، حقيبتين، محفظتين
- General: قطعتين، زوجين، علبتين، كرتونين، دزينتين
- Iraqi specific: صندلين، كبلين، بنطرونتين
```

### UNIT_WORDS (Measurement Units)
```php
قطع, حبه, حبة, علبه, علبة, زوج, طقم, كرتون, دزينه, دزينة, درزن, صندوق
```

### PLURAL_PATTERNS (Plural → Singular)
```php
بنطرونات→بنطرون, قمصان→قميص, فساتين→فستان, تيشرتات→تيشرت, احذيه→حذاء, شورتات→شورت
```

---

## Comprehensive extractQuantity() Method
8-step pattern matching:
1. Arabic digits (٣ = 3)
2. Standard digits (3)
3. NUM_WORDS dictionary
4. DUAL_PATTERNS (قميصين = 2)
5. "X + unit" patterns (3 قطع)
6. Compound Arabic (واحد وعشرين = 21)
7. PLURAL_PATTERNS with implied quantity
8. Contextual defaults

---

## New Handler Methods Added

### Pending State Handlers
```php
handlePendingAttributeResponse()  // Size/color follow-up
handleCustomerInfoConfirmation()  // Reuse existing info
handlePendingQuantity()           // "كم قطعة؟" response
handleImageRequestWithProduct()   // Specific product image
```

### Special Request Handlers
```php
requiresHumanAgent()        // Detect escalation needs (شكوى، مشكله، استرجاع)
handleHumanAgentRequest()   // Escalate to human
looksLikeQuantityOnly()     // Pure quantity messages (just "3")
```

### Product Attribute Flow
```php
askForMissingAttributes()   // Ask for size/color before order
buildCartPreview()          // Simple cart display
```

---

## Session State Management

### store_context Keys
```php
- pending_attribute_question: {cart_index, attribute_key, product_name}
- confirming_customer_info: bool
- customer_info_confirmed: bool
- pending_product: {id, name, price, stock}
- pending_image_request: bool
- last_mentioned_product: {id, name, price}
- shown_products: array
```

### processMessage() Flow (6-Step Pending Handling)
```php
1. Check pending_attribute_question → handlePendingAttributeResponse()
2. Check confirming_customer_info → handleCustomerInfoConfirmation()  
3. Check pending_product + looksLikeQuantityOnly → handlePendingQuantity()
4. Check pending_image_request → handleImageRequestWithProduct()
5. Check requiresHumanAgent() → handleHumanAgentRequest()
6. Normal intent detection
```

---

## Intent Detection Keywords

### HUMAN_AGENT_KEYWORDS (Escalation)
```php
شكوى، شكاوى، مشكله، مشكلة، استرجاع، استبدال، تالف، مكسور، غلط، خطأ، لم يصل، ما وصل، 
مايرد، ما يرد، اريد تكلم، اريد اتصال، بشري، موظف، خدمه عملاء، مدير، مسؤول
```

### NEW_ORDER_KEYWORDS (New Order After Completed)
```php
طلب جديد، اطلب مره ثانيه، من جديد، طلب ثاني، طلب اخر، اريد اطلب، ابي اطلب، مره اخرى
```

---

## Integration Points

### Services Used
- `AiProviderService` - AI model calls (OpenAI/Groq)
- `MissingDataDetector` - Product attribute validation
- `ProductAttributeService` - Size/color extraction
- `AiFastReply` - Custom reply templates

### Models Used
- `AiChatSession` - Conversation state + cart
- `Lead` - Customer info
- `Conversation` - Message thread
- `OnlineOrder` / `OnlineOrderItem` - Order persistence
- `Product` / `Category` - Store inventory

---

## Configuration (AiChatService Toggle)
```php
// In AiChatService.php
protected bool $useV2 = true;  // Set to true to use V2

public function processMessage(...) {
    if ($this->useV2) {
        return (new GroqChatServiceV2($this->user))
            ->processMessage($conversation, $lead, $message);
    }
    // ... original logic
}
```

---

## File Size
- **Lines**: 2458
- **Methods**: 50
- **Constants**: 10+

---

## Testing Recommendations
1. Test Arabic numbers 1-50 extraction
2. Test dual forms (قميصين = 2)
3. Test multi-item orders (قميصين وبنطرون)
4. Test order cancellation with status check
5. Test customer info confirmation flow
6. Test attribute (size/color) follow-up
7. Test human agent escalation
8. Test new order after completed
