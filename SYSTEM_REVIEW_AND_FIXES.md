# Rehla AI Chatbot - Full System Review & Fix Plan

## Date: 2026-02-18

---

## 🔴 CRITICAL ISSUES FOUND

### Issue 1: "متوفر قميص؟" Shows Categories Instead of Searching Products
**Location:** `IntentAnalyzer.php` KEYWORD_PATTERNS + `GroqChatServiceV3::handleBrowsing()`
**Problem:** Patterns like `/^متوفر\s+\S+/u` match to `BROWSE_PRODUCTS`, and `classifyBrowseQuery()` returns `'general'` when product name isn't extracted as a `category_name` entity. The system then shows categories only.
**Expected:** When user says "متوفر قميص؟", the system should search for "قميص" and show matching products.
**Fix:** In `handleBrowsing()`, when `browseType` is null (not general, not category), and a product name is extractable from the message, perform a product search instead of falling back to `showCategoriesOnly()`. Also, the `extractCategoryHint()` method does extract "قميص" from "متوفر قميص" correctly, but `findCategory()` may not match it. Need to ensure the flow falls through to product search.

### Issue 2: "سعره غالي / مابي تخفيض؟" Returns Generic Greeting  
**Location:** `GroqChatServiceV3::executeStateLogic()` → ChatAgent fallback → conversational fallback
**Problem:** When user says "بس سعره غالي مابي تخفيض؟", the `isConversationalMessage()` detects it as conversational. It delegates to ChatAgent. If ChatAgent fails or returns empty, `handleConversationalFallback()` handles price complaints. BUT if the message doesn't match any of the patterns well (e.g., "مابي تخفيض" = "don't you have a discount?"), it returns null, and the system shows a generic "ممكن توضح اكثر؟" or even worse, a greeting.
**Fix:** Add more negotiation/discount patterns to the conversational fallback: "تخفيض", "خصم", "عرض", "مابي". When detected with price context, respond with store discount policy.

### Issue 3: Repeated Same Response in Loop
**Location:** Session state management + `getOrCreateSession()`
**Problem:** The chat log shows the customer kept asking "شكد سعر الحزام؟" and got "ما لقيت" repeatedly. Then suddenly at 12:23 PM it worked. This suggests the session may have been in a state where product search (ASK_PRICE) was failing. The `extractProductFromMessage()` strips "شكد" and "سعر" leaving "الحزام" → "حزام" after stripping "ال". The `ProductService::search()` should find it. Issue is likely that `findBestMatch()` searched for "الحزام" (with ال) and the DB had "حزام تصحيح الظهر" - the LIKE search should still match because it uses `%حزام%`.
**Root Cause:** The `extractProductFromMessage()` in `GroqChatServiceV3` does NOT strip "شكد" or "سعر" from its strip list. It only strips browse-oriented words. So the extracted product name becomes "شكد سعر الحزام" after removing very few words, which doesn't match anything.
**Fix:** Add price-inquiry words to `extractProductFromMessage()`: "شكد", "سعر", "بكم", "كم", "كلش", "السعر".

### Issue 4: "متوفر قميص اخضر؟" Shows Only Categories
**Location:** `IntentAnalyzer::extractCategoryHint()` + browse handling
**Problem:** "متوفر قميص اخضر" is matched by pattern `/^متوفر\s+\S+/u` → BROWSE_PRODUCTS. The `extractCategoryHint()` extracts "قميص اخضر" but `findCategory()` doesn't find a category named "قميص اخضر". Then `classifyBrowseQuery()` returns 'category' (because extractCategoryHint found something), but `handleBrowsing()` tries to find the category and fails, then searches for products but the category_name entity is set, causing confusion.
**Fix:** When `browseType === 'category'` and category is not found in DB, fall through to product search with the extracted name instead of showing categories.

### Issue 5: ChatAgent (AI) Returns Greeting Instead of Contextual Response
**Location:** `ChatAgentService::buildSystemPrompt()` + AI fallback
**Problem:** When ChatAgent handles conversational messages like "سعره غالي", the AI sometimes returns a generic greeting instead of contextual response. The system prompt is good, but the conversation history passed to `getMessagesForAI()` may not include the product context.
**Fix:** Ensure the system prompt includes the current product context more prominently.

---

## 🟡 MODERATE ISSUES

### Issue 6: `extractProductFromMessage()` Missing Many Price/FAQ Words
Both `GroqChatServiceV3::extractProductFromMessage()` and `IntentAnalyzer::extractProductName()` have different strip lists. The V3 version is MISSING critical words like: "سعر", "شكد", "بكم", "كم", "صوره", "صور", "اضف", "حط" etc.

### Issue 7: `classifyBrowseQuery()` Doesn't Handle Product-Specific Browse
When user says "متوفر حزام؟", `classifyBrowseQuery()` checks for `extractCategoryHint()` first. If it returns a value (e.g., "حزام"), it returns 'category'. But "حزام" is a product, not a category. This causes the system to look for a category named "حزام", fail, and show "not found" with categories.

### Issue 8: Session Expiry 24h But State Not Reset
After 24h, `getOrCreateSession()` creates a new session. But if a session timed out between messages (e.g., user sends at 08:58 AM, then again at 12:23 PM), the old session is still found (within 24h), and its state may be stuck in a non-ideal state.

### Issue 9: REQUEST_IMAGE Conflicts with BROWSE_PRODUCTS
In KEYWORD_PATTERNS, both `/صوره/u` → REQUEST_IMAGE and `/وريني/u`/`/شوفني/u` → REQUEST_IMAGE are defined, BUT `/وريني/u`/`/شوفني/u` → BROWSE_PRODUCTS are also defined later. Since keyword matching stops at first match and these patterns appear BEFORE BROWSE_PRODUCTS patterns, "وريني" will always match REQUEST_IMAGE first.

### Issue 10: No Discount/Offer Response System
The user asks about discounts ("مابي تخفيض؟"), but there's no discount handler. The ChatAgent has a `get_offers_and_discounts` tool but it's not implemented to actually return anything useful.

---

## 🟢 IMPROVEMENTS NEEDED

### Improvement 1: Add "تخفيض/خصم/عرض" Intent
Create a new intent or handle discount requests in the conversational fallback. Currently, these go to UNKNOWN → ChatAgent which may not have discount data.

### Improvement 2: Better Context Persistence for Price Discussions
When user asks about price and then says "غالي", the system should remember what product was just shown and respond contextually without re-searching.

### Improvement 3: "شنو متوفر" vs "متوفر قميص"
Need better differentiation. "شنو متوفر" = general browse (categories). "متوفر قميص" = product search.

---

## 📋 FIX IMPLEMENTATION ORDER

1. **Fix `extractProductFromMessage()`** - Add missing price/inquiry words to strip list
2. **Fix `handleBrowsing()`** - When category not found, search as product  
3. **Fix conversational fallback** - Add discount/offer patterns
4. **Fix `classifyBrowseQuery()`** - Don't classify product names as categories
5. **Add discount/negotiation response patterns**
6. **Test all scenarios via Python script**
7. **Deploy and verify**
