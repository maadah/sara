<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$account = \App\Models\SocialAccount::where('provider', 'facebook_page')->first();
if (!$account) {
    echo "WARNING: No facebook_page account found in DB. Test might fail.\n";
    $pageId = '123456789';
} else {
    $pageId = $account->provider_id;
}

$payload = [
    'object' => 'page',
    'entry' => [
        [
            'id' => $pageId,
            'time' => time(),
            'messaging' => [
                [
                    'sender' => ['id' => '987654321'],
                    'recipient' => ['id' => $pageId],
                    'timestamp' => time(),
                    'message' => [
                        'mid' => 'mid.123',
                        'text' => 'مرحبا'
                    ]
                ]
            ]
        ]
    ]
];

$request = Illuminate\Http\Request::create(
    '/webhooks/meta',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode($payload)
);

$response = $kernel->handle($request);
echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Content: " . $response->getContent() . "\n";
