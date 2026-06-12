<?php

namespace App\Services;

use App\Models\ProxyApiLog;
use App\Models\ProxyPlatform;
use App\Models\ProxySocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyWebhookService
{
    /**
     * Check if a webhook page/account belongs to an external proxy platform.
     * If yes, forward the relevant entry to that platform's webhook URL.
     *
     * Returns true if forwarded (caller should NOT process it locally),
     * false if it belongs to our platform.
     */
    public function shouldForwardAndForward(string $providerId, array $fullPayload): bool
    {
        // Look up the provider_id in proxy_social_accounts
        $proxyAccount = ProxySocialAccount::where('provider_id', $providerId)
            ->whereHas('platform', fn($q) => $q->where('is_active', true))
            ->first();

        if (!$proxyAccount) {
            return false;
        }

        $platform = $proxyAccount->platform;

        // Forward the entire payload to the external platform's webhook
        $this->forwardWebhook($platform, $fullPayload, $providerId);

        return true;
    }

    /**
     * Forward webhook payload to the external platform.
     * Signed with HMAC so the receiver can verify authenticity.
     */
    protected function forwardWebhook(ProxyPlatform $platform, array $payload, string $providerId): void
    {
        if (empty($platform->webhook_url)) {
            return; // No webhook URL configured — skip forwarding
        }

        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $platform->api_secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Proxy-Signature'  => $signature,
                    'X-Proxy-Timestamp'  => $timestamp,
                    'X-Proxy-Platform'   => $platform->api_key,
                ])
                ->withBody($body, 'application/json')
                ->post($platform->webhook_url);

            $status = $response->successful() ? 'success' : 'error';

            Log::info("Proxy webhook forwarded to {$platform->name}", [
                'provider_id' => $providerId,
                'status'      => $response->status(),
                'success'     => $response->successful(),
            ]);

            ProxyApiLog::create([
                'proxy_platform_id' => $platform->id,
                'action'            => 'webhook_forward',
                'provider_id'       => $providerId,
                'status'            => $status,
                'details'           => $response->successful() ? null : $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error("Proxy webhook forward failed for {$platform->name}", [
                'provider_id' => $providerId,
                'error'       => $e->getMessage(),
            ]);

            ProxyApiLog::create([
                'proxy_platform_id' => $platform->id,
                'action'            => 'webhook_forward',
                'provider_id'       => $providerId,
                'status'            => 'error',
                'details'           => $e->getMessage(),
            ]);
        }
    }
}
