#!/usr/bin/env python3
"""
Rehla AI Chatbot - Comprehensive Testing Script
Tests all chatbot flows: search, buy, order, discussion, suggestions, agent, etc.

Usage:
    python test_chatbot.py [--user-id USER_ID] [--url URL]
"""

import requests
import json
import time
import sys
import argparse
from datetime import datetime

# ═══════════════════════════════════════════════════════════════
# CONFIGURATION
# ═══════════════════════════════════════════════════════════════

BASE_URL = "https://rihlaa-ai.com"
API_ENDPOINT = f"{BASE_URL}/api/ai-test-secure/chat"
RESET_ENDPOINT = f"{BASE_URL}/api/ai-test-secure/reset"
TEST_TOKEN = "rehla-test-2026-secure-key"
DEFAULT_USER_ID = 2  # Store owner user ID

HEADERS = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-Test-Token": TEST_TOKEN,
}

# Color codes for terminal output
class Colors:
    RED = "\033[91m"
    GREEN = "\033[92m"
    YELLOW = "\033[93m"
    BLUE = "\033[94m"
    CYAN = "\033[96m"
    BOLD = "\033[1m"
    END = "\033[0m"


# ═══════════════════════════════════════════════════════════════
# TEST SCENARIOS
# ═══════════════════════════════════════════════════════════════

SCENARIOS = {
    # ─── SCENARIO 1: Product Price Inquiry ───
    "price_inquiry": {
        "name": "🔍 Product Price Inquiry (شكد سعر الكيبورد)",
        "messages": [
            {
                "text": "شكد سعر الكيبورد؟",
                "expect_contains": ["كيبورد", "د.ع"],
                "expect_not_contains": ["ما لقيت", "الاقسام المتوفرة"],
                "description": "Ask for keyboard price - should find product, NOT show categories",
            },
        ],
    },

    # ─── SCENARIO 2: Browse Categories ───
    "browse_categories": {
        "name": "📂 Browse Categories (شنو عدكم)",
        "messages": [
            {
                "text": "شنو عدكم؟",
                "expect_contains": ["الاقسام المتوفرة", "•"],
                "description": "General browse - should show categories",
            },
        ],
    },

    # ─── SCENARIO 3: Product Availability Check ───
    "product_availability": {
        "name": "🔎 Product Availability (متوفر قميص؟)",
        "messages": [
            {
                "text": "متوفر قميص؟",
                "expect_contains_any": ["قميص", "د.ع", "السعر"],
                "expect_not_contains": ["ما لقيت", "الاقسام المتوفرة"],
                "description": "Check if shirt available - should search products, NOT just show categories",
            },
        ],
    },

    # ─── SCENARIO 4: Full Purchase Flow ───
    "full_purchase": {
        "name": "🛒 Full Purchase Flow",
        "messages": [
            {
                "text": "شكد سعر الكيبورد؟",
                "expect_contains": ["كيبورد", "د.ع"],
                "description": "Ask price",
            },
            {
                "text": "اضيفه للسله",
                "expect_contains_any": ["سلة", "سله", "✅", "تم اضافة"],
                "description": "Add to cart",
            },
            {
                "text": "تمام اكد الطلب",
                "expect_contains_any": ["الاسم", "الهاتف", "العنوان", "معلومات", "تأكيد"],
                "description": "Confirm order - should ask for info",
            },
            {
                "text": "احمد\n07812345678\nبغداد الكراده",
                "expect_contains_any": ["تأكيد", "الاسم", "احمد", "الطلب", "نعم"],
                "description": "Provide customer info",
            },
            {
                "text": "نعم",
                "expect_contains_any": ["تم تأكيد", "طلبك", "شكراً", "رقم الطلب"],
                "description": "Final confirmation",
            },
        ],
    },

    # ─── SCENARIO 5: Price Negotiation ───
    "price_negotiation": {
        "name": "💰 Price Negotiation (سعره غالي)",
        "messages": [
            {
                "text": "شكد سعر الكيبورد؟",
                "expect_contains": ["كيبورد", "د.ع"],
                "description": "Ask price first",
            },
            {
                "text": "سعره غالي",
                "expect_not_contains": ["ما لقيت", "الاقسام المتوفرة", "اهلاً وسهلاً! شلونك"],
                "expect_contains_any": ["غالي", "سعر", "جوده", "بديل", "ارخص", "يسوى", "منتج"],
                "description": "Price complaint - should respond contextually, NOT greeting or categories",
            },
            {
                "text": "مابي تخفيض؟",
                "expect_not_contains": ["اهلاً وسهلاً! شلونك"],
                "expect_contains_any": ["تخفيض", "خصم", "عرض", "سعر", "كيبورد", "منتج", "ساعد"],
                "description": "Ask for discount - should respond about pricing policy",
            },
        ],
    },

    # ─── SCENARIO 6: Category Browse ───
    "category_browse": {
        "name": "📋 Category Product Browse",
        "messages": [
            {
                "text": "شنو عدكم بالملابس الرجالية؟",
                "expect_contains_any": ["ملابس", "رجال", "د.ع", "1.", "قسم"],
                "description": "Browse specific category - should show products from that category",
            },
        ],
    },

    # ─── SCENARIO 7: Order Status ───
    "order_status": {
        "name": "📦 Order Status",
        "messages": [
            {
                "text": "وين طلبي؟",
                "expect_contains_any": ["طلب", "ما لقيت طلب", "رقم", "اسمك"],
                "description": "Check order status",
            },
        ],
    },

    # ─── SCENARIO 8: FAQ - Delivery ───
    "faq_delivery": {
        "name": "❓ FAQ - Delivery Cost",
        "messages": [
            {
                "text": "شكد سعر التوصيل؟",
                "expect_contains_any": ["التوصيل", "د.ع", "توصيل"],
                "description": "Ask about delivery cost",
            },
        ],
    },

    # ─── SCENARIO 9: Greeting ───
    "greeting": {
        "name": "👋 Greeting",
        "messages": [
            {
                "text": "السلام عليكم",
                "expect_contains_any": ["اهلاً", "وسهلاً", "اساعدك", "🌟"],
                "description": "Greeting should get warm response",
            },
        ],
    },

    # ─── SCENARIO 10: Product Detail Request ───
    "product_detail": {
        "name": "📝 Product Details",
        "messages": [
            {
                "text": "شكد سعر الكيبورد؟",
                "expect_contains": ["كيبورد", "د.ع"],
                "description": "Find product first",
            },
            {
                "text": "اشرحلي عن المنتج",
                "expect_contains_any": ["كيبورد", "منتج", "وصف", "تفاصيل", "بلوتوث"],
                "expect_not_contains": ["الاقسام المتوفرة", "ما لقيت"],
                "description": "Ask for details - should describe the product",
            },
        ],
    },

    # ─── SCENARIO 11: Cart Operations ───
    "cart_operations": {
        "name": "🛒 Cart Operations (add/view/update/remove)",
        "messages": [
            {
                "text": "اريد كيبورد بلوتوث صغير",
                "expect_contains_any": ["كيبورد", "سلة", "سله", "د.ع", "✅"],
                "description": "Add item to cart",
            },
            {
                "text": "شنو بالسله؟",
                "expect_contains_any": ["سلتك", "سله", "كيبورد", "د.ع"],
                "description": "View cart contents",
            },
            {
                "text": "سويهم 3",
                "expect_contains_any": ["3", "تحديث", "تم", "كمية", "قطعة"],
                "description": "Update quantity to 3",
            },
            {
                "text": "شيل الكيبورد من السله",
                "expect_contains_any": ["حذف", "تم", "شيل", "فاضي", "السله"],
                "description": "Remove item from cart",
            },
        ],
    },

    # ─── SCENARIO 12: Cancel Order ───
    "cancel_order": {
        "name": "❌ Cancel Order",
        "messages": [
            {
                "text": "اريد كيبورد",
                "expect_contains_any": ["كيبورد", "سلة", "سله", "✅"],
                "description": "Add to cart first",
            },
            {
                "text": "الغي الطلب",
                "expect_contains_any": ["إلغاء", "الغاء", "تم", "ملغ"],
                "description": "Cancel - should cancel order",
            },
        ],
    },

    # ─── SCENARIO 13: Image Request ───
    "image_request": {
        "name": "📷 Image Request",
        "messages": [
            {
                "text": "شكد سعر الكيبورد؟",
                "expect_contains": ["كيبورد"],
                "description": "Find product first",
            },
            {
                "text": "ابي صوره",
                "expect_contains_any": ["صور", "كيبورد", "ماكو صور"],
                "description": "Request product image",
            },
        ],
    },

    # ─── SCENARIO 14: What's Available (شنو متوفر) ───
    "what_available": {
        "name": "🔍 Whats Available",
        "messages": [
            {
                "text": "شنو متوفر؟",
                "expect_contains": ["الاقسام المتوفرة"],
                "description": "Should show categories",
            },
        ],
    },
}


# ═══════════════════════════════════════════════════════════════
# TEST ENGINE
# ═══════════════════════════════════════════════════════════════

class ChatTester:
    def __init__(self, base_url, user_id):
        self.base_url = base_url
        self.user_id = user_id
        self.api_endpoint = f"{base_url}/api/ai-test-secure/chat"
        self.reset_endpoint = f"{base_url}/api/ai-test-secure/reset"
        self.session_id = None
        self.results = []
        self.total_tests = 0
        self.passed_tests = 0
        self.failed_tests = 0
        self.errors = []

    def reset_session(self):
        """Reset test session"""
        self.session_id = None
        try:
            resp = requests.post(
                self.reset_endpoint,
                json={"user_id": self.user_id},
                headers=HEADERS,
                timeout=30,
                verify=False,
            )
            if resp.status_code == 200:
                print(f"  {Colors.CYAN}[Session Reset]{Colors.END}")
        except Exception as e:
            print(f"  {Colors.YELLOW}[Reset Warning: {e}]{Colors.END}")

    def send_message(self, message):
        """Send a message to the chatbot and return response"""
        payload = {
            "user_id": self.user_id,
            "message": message,
        }
        if self.session_id:
            payload["session_id"] = self.session_id

        try:
            resp = requests.post(
                self.api_endpoint,
                json=payload,
                headers=HEADERS,
                timeout=60,
                verify=False,
            )

            if resp.status_code != 200:
                return {
                    "success": False,
                    "error": f"HTTP {resp.status_code}: {resp.text[:200]}",
                    "response": None,
                }

            data = resp.json()

            # Save session ID for conversation continuity
            if data.get("session_id"):
                self.session_id = data["session_id"]

            return {
                "success": data.get("success", False),
                "response": data.get("response", ""),
                "cart": data.get("cart", []),
                "cart_total": data.get("cart_total", 0),
                "customer_data": data.get("customer_data", {}),
                "order_created": data.get("order_created"),
                "duration_ms": data.get("duration_ms", 0),
                "error": data.get("error"),
            }

        except requests.exceptions.Timeout:
            return {"success": False, "error": "Request timed out (60s)", "response": None}
        except requests.exceptions.ConnectionError as e:
            return {"success": False, "error": f"Connection error: {e}", "response": None}
        except Exception as e:
            return {"success": False, "error": str(e), "response": None}

    def check_expectations(self, response_text, step):
        """Check if response meets expectations"""
        issues = []
        if not response_text:
            issues.append("Empty response!")
            return issues

        # Check expect_contains (ALL must be present)
        for keyword in step.get("expect_contains", []):
            if keyword not in response_text:
                issues.append(f"Missing expected keyword: '{keyword}'")

        # Check expect_contains_any (AT LEAST ONE must be present)
        any_keywords = step.get("expect_contains_any", [])
        if any_keywords:
            found = any(kw in response_text for kw in any_keywords)
            if not found:
                issues.append(f"None of expected keywords found: {any_keywords}")

        # Check expect_not_contains (NONE should be present)
        for keyword in step.get("expect_not_contains", []):
            if keyword in response_text:
                issues.append(f"Unexpected keyword found: '{keyword}'")

        return issues

    def run_scenario(self, scenario_key, scenario):
        """Run a single test scenario"""
        print(f"\n{'═'*60}")
        print(f"{Colors.BOLD}{scenario['name']}{Colors.END}")
        print(f"{'═'*60}")

        self.reset_session()
        time.sleep(1)  # Brief delay between scenarios

        scenario_passed = True
        scenario_results = []

        for i, step in enumerate(scenario["messages"]):
            self.total_tests += 1
            msg = step["text"]
            desc = step.get("description", "")

            print(f"\n  {Colors.BLUE}Step {i+1}: {desc}{Colors.END}")
            print(f"  📤 {msg}")

            result = self.send_message(msg)

            if not result["success"]:
                print(f"  {Colors.RED}❌ ERROR: {result.get('error', 'Unknown')}{Colors.END}")
                self.failed_tests += 1
                scenario_passed = False
                self.errors.append(f"[{scenario_key}] Step {i+1}: {result.get('error')}")
                scenario_results.append({
                    "step": i + 1,
                    "message": msg,
                    "status": "ERROR",
                    "error": result.get("error"),
                })
                continue

            response_text = result["response"] or ""
            duration = result.get("duration_ms", 0)

            # Truncate for display
            display_response = response_text[:300] + ("..." if len(response_text) > 300 else "")
            print(f"  📥 {display_response}")
            print(f"  ⏱ {duration}ms")

            # Check expectations
            issues = self.check_expectations(response_text, step)

            if issues:
                self.failed_tests += 1
                scenario_passed = False
                print(f"  {Colors.RED}❌ FAILED:{Colors.END}")
                for issue in issues:
                    print(f"    - {issue}")
                    self.errors.append(f"[{scenario_key}] Step {i+1}: {issue}")
            else:
                self.passed_tests += 1
                print(f"  {Colors.GREEN}✅ PASSED{Colors.END}")

            scenario_results.append({
                "step": i + 1,
                "message": msg,
                "response": response_text,
                "duration_ms": duration,
                "issues": issues,
                "status": "PASS" if not issues else "FAIL",
            })

            # Brief delay between messages in same conversation
            time.sleep(2)

        return scenario_passed, scenario_results

    def run_all(self, specific_scenarios=None):
        """Run all (or specific) test scenarios"""
        print(f"\n{'╔'+'═'*58+'╗'}")
        print(f"{'║'} {Colors.BOLD}REHLA AI CHATBOT - COMPREHENSIVE TEST{Colors.END}{'║':>20}")
        print(f"{'║'} URL: {self.base_url}{'║':>42}")
        print(f"{'║'} User ID: {self.user_id}{'║':>48}")
        print(f"{'║'} Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}{'║':>37}")
        print(f"{'╚'+'═'*58+'╝'}")

        scenarios_to_run = specific_scenarios or list(SCENARIOS.keys())
        all_results = {}

        for key in scenarios_to_run:
            if key not in SCENARIOS:
                print(f"\n{Colors.YELLOW}⚠ Unknown scenario: {key}{Colors.END}")
                continue

            passed, results = self.run_scenario(key, SCENARIOS[key])
            all_results[key] = {
                "passed": passed,
                "results": results,
            }

        # Print summary
        self.print_summary(all_results)
        return all_results

    def print_summary(self, all_results):
        """Print test summary"""
        print(f"\n\n{'╔'+'═'*58+'╗'}")
        print(f"{'║'} {Colors.BOLD}TEST SUMMARY{Colors.END}{'║':>46}")
        print(f"{'╠'+'═'*58+'╣'}")
        print(f"{'║'} Total Tests: {self.total_tests:>3}{'║':>42}")
        print(f"{'║'} {Colors.GREEN}Passed: {self.passed_tests:>3}{Colors.END}{'║':>51}")
        print(f"{'║'} {Colors.RED}Failed: {self.failed_tests:>3}{Colors.END}{'║':>51}")

        success_rate = (self.passed_tests / self.total_tests * 100) if self.total_tests > 0 else 0
        rate_color = Colors.GREEN if success_rate >= 80 else (Colors.YELLOW if success_rate >= 50 else Colors.RED)
        print(f"{'║'} {rate_color}Success Rate: {success_rate:.1f}%{Colors.END}{'║':>47}")
        print(f"{'╠'+'═'*58+'╣'}")

        # Per-scenario summary
        for key, data in all_results.items():
            status = f"{Colors.GREEN}✅ PASS{Colors.END}" if data["passed"] else f"{Colors.RED}❌ FAIL{Colors.END}"
            name = SCENARIOS[key]["name"][:40]
            print(f"{'║'} {status} {name}")

        print(f"{'╠'+'═'*58+'╣'}")

        if self.errors:
            print(f"{'║'} {Colors.RED}ISSUES FOUND:{Colors.END}")
            for err in self.errors[:20]:  # Show max 20 issues
                print(f"{'║'}   • {err[:55]}")

        print(f"{'╚'+'═'*58+'╝'}")


# ═══════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════

def main():
    parser = argparse.ArgumentParser(description="Rehla AI Chatbot Tester")
    parser.add_argument("--url", default=BASE_URL, help="Base URL")
    parser.add_argument("--user-id", type=int, default=DEFAULT_USER_ID, help="Store user ID")
    parser.add_argument("--scenario", type=str, default=None, help="Run specific scenario")
    parser.add_argument("--list", action="store_true", help="List available scenarios")
    args = parser.parse_args()

    if args.list:
        print("Available scenarios:")
        for key, val in SCENARIOS.items():
            print(f"  {key}: {val['name']} ({len(val['messages'])} steps)")
        return

    # Suppress SSL warnings
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    tester = ChatTester(args.url, args.user_id)

    if args.scenario:
        tester.run_all(specific_scenarios=[args.scenario])
    else:
        tester.run_all()


if __name__ == "__main__":
    main()
