@extends('proxy.admin.layout')
@section('title', 'Integration Docs')

@section('content')
<style>
    .doc-section { margin-bottom: 32px; }
    .doc-section h2 { font-size: 1.15rem; color: #f1f5f9; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #334155; }
    .doc-section h3 { font-size: .95rem; color: #e2e8f0; margin: 16px 0 8px; }
    .doc-section p, .doc-section li { font-size: .9rem; color: #94a3b8; line-height: 1.7; }
    .doc-section ul { padding-left: 20px; }
    .doc-section code { background: #0f172a; color: #38bdf8; padding: 2px 6px; border-radius: 4px; font-size: .85rem; }
    .code-block { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 16px; overflow-x: auto; margin: 10px 0 16px; }
    .code-block pre { margin: 0; color: #e2e8f0; font-size: .82rem; line-height: 1.6; white-space: pre; }
    .endpoint { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
    .endpoint .method { display: inline-block; font-weight: 700; font-size: .8rem; padding: 2px 8px; border-radius: 4px; margin-right: 8px; }
    .endpoint .method-get { background: #16a34a33; color: #4ade80; }
    .endpoint .method-post { background: #2563eb33; color: #60a5fa; }
    .endpoint .url { font-family: monospace; font-size: .85rem; color: #e2e8f0; }
</style>

<h2 style="font-size: 1.3rem; color: #f1f5f9; margin-bottom: 24px;">Integration Documentation</h2>

{{-- Overview --}}
<div class="doc-section">
    <h2>Overview</h2>
    <p>This proxy system allows your platform to use our approved Facebook/Meta app to handle OAuth logins, receive webhooks, and send messages — without needing your own Meta app review.</p>
    <p>The flow works in 3 parts:</p>
    <ul>
        <li><strong>OAuth Proxy</strong> — Redirect your users here to link their Facebook pages. We handle the entire OAuth flow and redirect back to your platform with credentials.</li>
        <li><strong>Webhook Forwarding</strong> — When Facebook sends webhooks for pages linked through your platform, we automatically forward them to your webhook URL with HMAC signature verification.</li>
        <li><strong>API Proxy</strong> — Send messages, images, and comment replies through our API using signed requests.</li>
    </ul>
</div>

{{-- Auth --}}
<div class="doc-section">
    <h2>1. Authentication (HMAC-SHA256)</h2>
    <p>All API requests must include these headers:</p>
    <div class="code-block"><pre>X-Api-Key: your_api_key
X-Api-Timestamp: unix_timestamp
X-Api-Signature: hmac_sha256(api_key + timestamp + request_body, api_secret)</pre></div>
    <p>The signature is computed as: <code>HMAC-SHA256(api_key + timestamp + body, api_secret)</code>. The timestamp must be within 5 minutes of server time.</p>

    <h3>PHP Example</h3>
    <div class="code-block"><pre>$apiKey    = 'your_api_key';
$apiSecret = 'your_api_secret';
$timestamp = (string) time();
$body      = json_encode($payload);

$signature = hash_hmac('sha256', $apiKey . $timestamp . $body, $apiSecret);

$headers = [
    'Content-Type: application/json',
    'X-Api-Key: '       . $apiKey,
    'X-Api-Timestamp: '  . $timestamp,
    'X-Api-Signature: '  . $signature,
];</pre></div>

    <h3>Node.js Example</h3>
    <div class="code-block"><pre>const crypto = require('crypto');

const apiKey    = 'your_api_key';
const apiSecret = 'your_api_secret';
const timestamp = Math.floor(Date.now() / 1000).toString();
const body      = JSON.stringify(payload);

const signature = crypto
    .createHmac('sha256', apiSecret)
    .update(apiKey + timestamp + body)
    .digest('hex');

const headers = {
    'Content-Type':     'application/json',
    'X-Api-Key':        apiKey,
    'X-Api-Timestamp':  timestamp,
    'X-Api-Signature':  signature,
};</pre></div>
</div>

{{-- OAuth Flow --}}
<div class="doc-section">
    <h2>2. OAuth Flow (Link Facebook Pages)</h2>
    <p>Redirect the user's browser to start the OAuth flow:</p>

    <div class="endpoint">
        <span class="method method-get">GET</span>
        <span class="url">{{ url('/proxy/auth/start') }}?api_key=YOUR_KEY&external_user_id=USER_123</span>
    </div>

    <p><strong>Parameters:</strong></p>
    <ul>
        <li><code>api_key</code> — Your platform's API key</li>
        <li><code>external_user_id</code> — The user's ID on your platform</li>
    </ul>

    <p><strong>Flow:</strong></p>
    <ul>
        <li>User is redirected to Facebook to authorize page access</li>
        <li>We save the page tokens and subscribe to webhooks</li>
        <li>User is redirected back to your <code>oauth_callback_url</code> with signed parameters</li>
    </ul>

    <p><strong>Callback Parameters (GET):</strong></p>
    <div class="code-block"><pre>https://your-callback-url?
    external_user_id=USER_123
    &pages=[{"id":"123","name":"My Page"}]
    &timestamp=1710000000
    &signature=hmac_sha256(external_user_id + pages_json + timestamp, api_secret)</pre></div>

    <h3>Verify Callback Signature (PHP)</h3>
    <div class="code-block"><pre>$externalUserId = $_GET['external_user_id'];
$pages          = $_GET['pages'];
$timestamp      = $_GET['timestamp'];
$signature      = $_GET['signature'];

$expected = hash_hmac('sha256', $externalUserId . $pages . $timestamp, $apiSecret);

if (!hash_equals($expected, $signature)) {
    die('Invalid signature');
}

$linkedPages = json_decode($pages, true);
// Save the linked pages for this user</pre></div>
</div>

{{-- Webhooks --}}
<div class="doc-section">
    <h2>3. Receiving Webhooks</h2>
    <p>When Facebook sends events for pages linked through your platform, we forward the raw webhook payload to your <code>webhook_url</code> via POST with these headers:</p>

    <div class="code-block"><pre>X-Proxy-Signature: hmac_sha256(request_body, api_secret)
X-Proxy-Timestamp: unix_timestamp
X-Proxy-Platform: your_platform_name</pre></div>

    <h3>Verify Webhook (PHP)</h3>
    <div class="code-block"><pre>$body      = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PROXY_SIGNATURE'] ?? '';
$expected  = hash_hmac('sha256', $body, $apiSecret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}

$data = json_decode($body, true);
// Process the Facebook webhook data</pre></div>

    <p><strong>Webhook payload structure</strong> — identical to Facebook's format:</p>
    <div class="code-block"><pre>{
    "object": "page",
    "entry": [
        {
            "id": "PAGE_ID",
            "time": 1710000000,
            "messaging": [
                {
                    "sender": { "id": "USER_PSID" },
                    "recipient": { "id": "PAGE_ID" },
                    "message": { "text": "Hello!" }
                }
            ]
        }
    ]
}</pre></div>
</div>

{{-- API Endpoints --}}
<div class="doc-section">
    <h2>4. API Endpoints</h2>

    {{-- Send Message --}}
    <h3>Send Text Message</h3>
    <div class="endpoint">
        <span class="method method-post">POST</span>
        <span class="url">{{ url('/proxy/api/send-message') }}</span>
    </div>
    <div class="code-block"><pre>{
    "page_id": "PAGE_ID",
    "recipient_id": "USER_PSID",
    "message": "Hello from our platform!"
}</pre></div>

    {{-- Send Image --}}
    <h3>Send Image</h3>
    <div class="endpoint">
        <span class="method method-post">POST</span>
        <span class="url">{{ url('/proxy/api/send-image') }}</span>
    </div>
    <div class="code-block"><pre>{
    "page_id": "PAGE_ID",
    "recipient_id": "USER_PSID",
    "image_url": "https://example.com/image.jpg"
}</pre></div>

    {{-- Reply Comment --}}
    <h3>Reply to Comment</h3>
    <div class="endpoint">
        <span class="method method-post">POST</span>
        <span class="url">{{ url('/proxy/api/reply-comment') }}</span>
    </div>
    <div class="code-block"><pre>{
    "page_id": "PAGE_ID",
    "comment_id": "COMMENT_ID",
    "message": "Thank you for your comment!"
}</pre></div>

    {{-- List Pages --}}
    <h3>List Linked Pages</h3>
    <div class="endpoint">
        <span class="method method-get">GET</span>
        <span class="url">{{ url('/proxy/api/pages') }}</span>
    </div>
    <p>Returns all pages linked to your platform. Add <code>?external_user_id=USER_123</code> to filter by user.</p>
    <p><em>Note: For GET requests, set body to empty string when computing the HMAC signature.</em></p>

    <h3>Response Format</h3>
    <p>All API responses follow this format:</p>
    <div class="code-block"><pre>// Success
{ "success": true, "data": { ... } }

// Error
{ "success": false, "error": "Error message" }</pre></div>
</div>

{{-- Error Codes --}}
<div class="doc-section">
    <h2>5. Error Codes</h2>
    <table style="width: 100%; font-size: .85rem;">
        <thead>
            <tr>
                <th style="text-align: left; padding: 8px; color: #94a3b8; border-bottom: 1px solid #334155;">HTTP</th>
                <th style="text-align: left; padding: 8px; color: #94a3b8; border-bottom: 1px solid #334155;">Meaning</th>
            </tr>
        </thead>
        <tbody>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">200</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Success</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">401</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Invalid or missing API key / signature</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">403</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Platform is deactivated or page not linked to your platform</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">404</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Page not found for this platform</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">422</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Validation error (missing fields)</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #1e293b;">500</td><td style="padding: 8px; border-bottom: 1px solid #1e293b;">Facebook API error (details in response)</td></tr>
        </tbody>
    </table>
</div>

{{-- Quick Start --}}
<div class="doc-section">
    <h2>6. Quick Start Checklist</h2>
    <ul>
        <li>Get your <code>api_key</code> and <code>api_secret</code> from the platform details page</li>
        <li>Set up your <strong>webhook endpoint</strong> to receive forwarded Facebook events</li>
        <li>Implement <strong>HMAC signature verification</strong> on your webhook endpoint</li>
        <li>Add a "Connect Facebook" button that redirects to the OAuth start URL</li>
        <li>Handle the OAuth callback — verify signature, save linked page IDs</li>
        <li>Use the API to send messages when you need to respond to users</li>
    </ul>
</div>
@endsection
