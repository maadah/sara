#!/usr/bin/env python3
"""
AI Marketing Chatbot — Full Python Test Suite
Tests the Laravel API endpoint and OpenAI integration.

Usage:
    py test_ai.py

Requirements: Python 3.7+ (stdlib only — no external packages needed)
"""

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

# ─── Config ──────────────────────────────────────────────────────────────────
BASE_URL   = "http://127.0.0.1:8765"
API_CHAT   = f"{BASE_URL}/api/v1/chat"
API_STATS  = f"{BASE_URL}/api/v1/chat/session"
STORE_ID   = 2    # from diagnostic: zaid osama
LEAD_ID    = 1    # from diagnostic: first lead
TIMEOUT    = 45   # seconds per request

# Read .env for OpenAI key (used in direct API tests)
def read_env(key: str) -> str:
    env_file = Path(__file__).parent / ".env"
    if not env_file.exists():
        return ""
    for line in env_file.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line.startswith(f"{key}=") and not line.startswith("#"):
            return line[len(key) + 1:].strip().strip('"')
    return ""

OPENAI_KEY        = read_env("OPENAI_API_KEY")
OPENAI_BASE       = read_env("OPENAI_BASE_URL") or "https://api.openai.com/v1"
CONV_MODEL        = read_env("CHAT_CONVERSATION_MODEL") or "gpt-4.1-mini"
CLASS_MODEL       = read_env("CHAT_CLASSIFICATION_MODEL") or "gpt-4.1-nano"

# ─── Helpers ─────────────────────────────────────────────────────────────────
passed = 0
failed = 0

def ok(label: str, detail: str = "") -> None:
    global passed
    print(f"  \u2705 {label}" + (f"\n       {detail}" if detail else ""))
    passed += 1

def fail(label: str, reason: str = "") -> None:
    global failed
    print(f"  \u274C {label}" + (f"\n       Reason: {reason}" if reason else ""))
    failed += 1

def info(text: str) -> None:
    print(f"     {text}")

def hr(title: str = "") -> None:
    print("\n" + "-" * 64)
    if title:
        print(f"  {title}")
        print("-" * 64)

def check(cond: bool, label: str, reason: str = "") -> bool:
    if cond:
        ok(label)
    else:
        fail(label, reason)
    return cond

def post_json(url: str, payload: dict) -> dict:
    data = json.dumps(payload).encode("utf-8")
    req  = urllib.request.Request(
        url,
        data    = data,
        headers = {"Content-Type": "application/json", "Accept": "application/json"},
        method  = "POST",
    )
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8"))

def get_json(url: str, headers: dict = None) -> dict:
    req = urllib.request.Request(url, headers=headers or {}, method="GET")
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8"))

def send_message(message: str, conv_id: str = None) -> dict:
    payload = {
        "store_id": STORE_ID,
        "lead_id":  LEAD_ID,
        "message":  message,
        "channel":  "web",
    }
    if conv_id:
        payload["conversation_id"] = conv_id
    return post_json(API_CHAT, payload)

# =============================================================================
hr("TEST 1 — Server Health")

try:
    res = urllib.request.urlopen(f"{BASE_URL}/up", timeout=5)
    check(res.status == 200, f"Laravel server is UP at {BASE_URL}")
except Exception as e:
    fail(f"Laravel server not reachable at {BASE_URL}", str(e))
    print("\n  \u26A0\uFE0F  Start the server first: php artisan serve --port=8765")
    sys.exit(1)

# =============================================================================
hr("TEST 2 — OpenAI API Direct Check")

if not OPENAI_KEY:
    fail("OPENAI_API_KEY not set", "Not found in .env")
else:
    check(True, "OPENAI_API_KEY found in .env")
    info(f"Key prefix   : {OPENAI_KEY[:8]}***")
    info(f"Conv model   : {CONV_MODEL}")
    info(f"Class model  : {CLASS_MODEL}")

    try:
        data = json.dumps({
            "model":      CONV_MODEL,
            "messages":   [{"role": "user", "content": "قل مرحبا فقط"}],
            "max_tokens": 20,
        }).encode()
        req = urllib.request.Request(
            f"{OPENAI_BASE}/chat/completions",
            data    = data,
            headers = {
                "Authorization": f"Bearer {OPENAI_KEY}",
                "Content-Type":  "application/json",
            },
        )
        with urllib.request.urlopen(req, timeout=30) as r:
            body  = json.loads(r.read())
            reply = body["choices"][0]["message"]["content"]
            tokens = body["usage"]["total_tokens"]
            check(bool(reply), f"OpenAI replied: {reply[:40]}")
            info(f"Tokens used  : {tokens}")
    except urllib.error.HTTPError as e:
        err_body = json.loads(e.read())
        fail(f"OpenAI HTTP {e.code}", err_body.get("error", {}).get("message", str(e))[:120])
    except Exception as e:
        fail("OpenAI API exception", str(e))

# =============================================================================
hr("TEST 3 — API Validation (should return 422)")

try:
    data = json.dumps({}).encode()
    req  = urllib.request.Request(
        API_CHAT,
        data    = data,
        headers = {"Content-Type": "application/json"},
        method  = "POST",
    )
    try:
        urllib.request.urlopen(req, timeout=10)
        fail("Expected 422 for empty body, but got 200")
    except urllib.error.HTTPError as e:
        if e.code == 422:
            body = json.loads(e.read())
            check(True, "Empty body correctly returns 422")
            check("errors" in body, "Response has 'errors' field")
        else:
            fail(f"Unexpected status {e.code}")
except Exception as e:
    fail("Validation test exception", str(e))

# =============================================================================
hr("TEST 4 — Single Greeting")

print("\n  \U0001F4E8 Sending: 'مرحبا'")
try:
    start  = time.time()
    result = send_message("مرحبا")
    dur    = round(time.time() - start, 1)
    ok(f"Request completed in {dur}s")

    check(result.get("success") is True, "Response.success = true")
    data = result.get("data", {})
    reply = data.get("reply", "")

    check(bool(reply), "Reply is not empty", "Got empty string")
    check(not reply.startswith("chat."), "Reply is real text (not translation key)",
          f"Got key: {reply}")
    check(len(reply) > 10, f"Reply has meaningful length ({len(reply)} chars)")

    # Iraqi Arabic check
    arabic_chars = len(re.findall(r'[\u0600-\u06FF]', reply))
    check(arabic_chars > 5, f"Reply contains Arabic text ({arabic_chars} Arabic chars)")

    info(f"Session ID : {data.get('session_id')}")
    info(f"Reply      : {reply[:120]}")

    session_id = data.get("session_id")

except Exception as e:
    fail("Greeting test exception", str(e))
    session_id = None

# =============================================================================
hr("TEST 5 — Full Conversation Flow (5 turns)")

conversation_script = [
    ("مرحبا كيفك",                          "greeting"),
    ("شنو عدكم من منتجات؟",                  "browse general"),
    ("ابي اشوف ادوات منزلية",                "browse category"),
    ("كم سعر المنتج الاول؟",                 "ask price"),
    ("في توصيل لبغداد؟",                     "ask delivery"),
]

print("")
conv_session = None
for message, label in conversation_script:
    print(f"\n  \U0001F4E8 [{label}] \"{message}\"")
    try:
        start  = time.time()
        result = send_message(message)
        dur    = round(time.time() - start, 1)

        if not result.get("success"):
            fail(f"Turn failed: {label}", str(result))
            continue

        data  = result["data"]
        reply = data.get("reply", "")
        imgs  = len(data.get("images", []))
        prods = len(data.get("products", []))

        if conv_session is None:
            conv_session = data.get("session_id")

        is_real = bool(reply) and not reply.startswith("chat.")
        check(is_real, f"Got real reply ({dur}s)", f"Empty or key: {reply[:40]}")
        info(f"Reply: {reply[:100]}")
        if imgs:  info(f"Images returned: {imgs}")
        if prods: info(f"Products returned: {prods}")

    except Exception as e:
        fail(f"Turn exception: {label}", str(e))

# =============================================================================
hr("TEST 6 — Session Stats")

if conv_session:
    try:
        stats = get_json(f"{API_STATS}/{conv_session}")
        check(stats.get("success") is True, "Session stats endpoint works")
        d = stats.get("data", {})
        check(d.get("total_messages", 0) > 0, f"Messages recorded: {d.get('total_messages')}")
        info(f"Session outcome : {d.get('outcome')}")
        info(f"Total tokens    : {d.get('total_tokens')}")
        info(f"Estimated cost  : ${d.get('estimated_cost')} USD")
        info(f"State           : {d.get('state')}")
    except Exception as e:
        fail("Session stats exception", str(e))
else:
    info("Skipping session stats (no session_id captured)")

# =============================================================================
hr("TEST 7 — Negative Feedback Handling")

negative_messages = [
    "المنتج مو زين ما عجبني",
    "في مشكله بالطلب",
]

for msg in negative_messages:
    print(f"\n  \U0001F4E8 Negative: \"{msg}\"")
    try:
        result = send_message(msg)
        data   = result.get("data", {})
        reply  = data.get("reply", "")
        check(bool(reply) and not reply.startswith("chat."), f"Got reply for negative feedback")
        # Should contain apology or empathy
        apology_words = ["آسف", "اسف", "عذرا", "عذراً", "اسفين", "نعتذر", "مشكلة", "مشكله", "نساعد", "نحل"]
        has_empathy = any(w in reply for w in apology_words)
        if has_empathy:
            ok("Reply contains empathy/apology keywords")
        else:
            info(f"Reply (no explicit apology detected): {reply[:100]}")
    except Exception as e:
        fail(f"Negative feedback exception", str(e))

# =============================================================================
hr("TEST 8 — Cart Flow")

cart_flow = [
    "ضيف المنتج الاول للسلة",
    "اعرضلي السلة",
]

for msg in cart_flow:
    print(f"\n  \U0001F6D2 Cart: \"{msg}\"")
    try:
        result = send_message(msg)
        data   = result.get("data", {})
        reply  = data.get("reply", "")
        check(bool(reply) and not reply.startswith("chat."), f"Cart reply received")
        info(f"Reply: {reply[:100]}")
    except Exception as e:
        fail("Cart flow exception", str(e))

# =============================================================================
hr("SUMMARY")

total     = passed + failed
pass_rate = round(passed / total * 100) if total else 0
print(f"\n  Passed: {passed}  Failed: {failed}  Total: {total}  Pass rate: {pass_rate}%\n")

if failed == 0:
    print("  ALL TESTS PASSED -- Chatbot is fully operational!\n")
    sys.exit(0)
else:
    print("  Some tests failed -- review the issues above.\n")
    sys.exit(1)
