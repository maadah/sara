# AI Chat Testing System

## Quick Start

### Run All Tests
```bash
php artisan ai:test --all --user=2
```

### Run Specific Scenario
```bash
php artisan ai:test --scenario=greeting --user=2
```

### List Available Scenarios
```bash
php artisan ai:test --list
```

### Interactive Chat Mode
```bash
php artisan ai:test --chat --user=2
```

## API Endpoints

The testing system also exposes REST APIs:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/ai-test/chat` | POST | Send single message |
| `/api/ai-test/scenario` | POST | Run specific scenario |
| `/api/ai-test/full-test` | POST | Run ALL scenarios |
| `/api/ai-test/scenarios` | GET | List all scenarios |
| `/api/ai-test/reset` | POST | Clear test data |

### Example API Usage

```bash
# Single message
curl -X POST http://localhost/api/ai-test/chat \
  -H "Content-Type: application/json" \
  -d '{"user_id": 2, "message": "السلام عليكم"}'

# Run scenario
curl -X POST http://localhost/api/ai-test/scenario \
  -H "Content-Type: application/json" \
  -d '{"user_id": 2, "scenario": "greeting"}'
```

## Python Script

A Python test script is also available:

```bash
# Interactive chat
python test_ai.py --chat --user=2

# Run all tests
python test_ai.py --all --user=2

# Quick essential tests only
python test_ai.py --quick --user=2
```

## Test Scenarios (51 total)

### Full Order Flows (4)
- `full_order_iraqi` - Complete order in Iraqi dialect
- `full_order_egyptian` - Complete order in Egyptian dialect
- `full_order_gulf` - Complete order in Gulf dialect
- `full_order_levantine` - Complete order in Levantine dialect

### Product Search (3)
- `search_by_category` - Search by product category
- `search_cheapest` - Find cheapest product
- `product_details` - Ask about product details

### Quantity Formats (4)
- `quantity_arabic_numerals` - Using ٥ format
- `quantity_english_numerals` - Using 5 format
- `quantity_words` - Using ثلاثة format
- `quantity_dual` - Using جهازين format

### Cart Operations (4)
- `add_multiple_products` - Add different items
- `modify_quantity` - Change item quantity
- `remove_from_cart` - Remove single item
- `clear_cart` - Clear entire cart

### Confirmation Keywords (6)
- `confirm_with_yes` - نعم
- `confirm_with_tamam` - تمام
- `confirm_with_continue` - استمر
- `confirm_with_mashi` - ماشي
- `confirm_with_khalas` - خلاص
- `confirm_with_zain` - زين

### Dialects (5)
- `dialect_iraqi_want` - اريد
- `dialect_egyptian_want` - عايز
- `dialect_gulf_want` - ابغى
- `dialect_levantine_want` - بدي
- `dialect_saudi_want` - ودي

### Edge Cases (5)
- `unavailable_product` - Ask for non-existent item
- `gibberish` - Send nonsense text
- `very_long_message` - Long query
- `emoji_message` - Messages with emojis
- `mixed_language` - Arabic/English mix

### Plural Forms (3)
- `plural_regular` - نظارات (regular)
- `plural_irregular` - حقائب (broken plural)
- `plural_belts` - احزمة (broken plural)

### Spelling Variations (2)
- `spelling_za_da` - نضارات vs نظارات
- `spelling_ta_ha` - حقيبه vs حقيبة

### Store Info (4)
- `ask_delivery` - Delivery questions
- `ask_return_policy` - Return policy
- `ask_payment` - Payment methods
- `ask_working_hours` - Working hours

## Understanding Test Results

### Pass (✅)
The scenario completed successfully and all expectations were met.

### Fail (❌)
One or more expectations failed. Common failure reasons:
- `Cart should not be empty` - Product wasn't added
- `Customer name should be set` - Name parsing failed
- `Expected X cart items, got Y` - Wrong item count

## Current Test Status

Last run: **62.7% pass rate** (32/51 scenarios)

### Known Issues Being Worked On:
1. Customer info parsing (multi-line messages)
2. Word quantity formats (ثلاثة)
3. Some irregular plural forms
4. Egyptian dialect edge cases

## Before Facebook Deployment

Always run:
```bash
php artisan ai:test --all --user=YOUR_USER_ID
```

Target: **>90% pass rate** before deployment
