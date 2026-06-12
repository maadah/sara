<?php

namespace App\Services;

use App\Models\User;
use App\Models\Lead;
use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InvenGptService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.invengpt.url', 'http://127.0.0.1:5000');
        $this->timeout = (int) config('services.invengpt.timeout', 60);
    }

    /**
     * Create a new chat session for a lead
     *
     * @param int $storeId
     * @param int $leadId
     * @return array|null ['session_id' => string, 'expires_in_minutes' => int, 'is_existing' => bool]
     */
    public function createSession(int $storeId, int $leadId): ?array
    {
        try {
            $store = User::with('aiSetting')->find($storeId);
            $lead = Lead::with('conversation')->find($leadId);

            if (!$store || !$lead) {
                Log::error('InvenGPT: Store or Lead not found', [
                    'store_id' => $storeId,
                    'lead_id' => $leadId,
                ]);
                return null;
            }

            // Get active products (limit to 50 for better catalog coverage)
            $products = Product::where('user_id', $storeId)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->orderBy('quantity', 'desc')
                ->limit(50)
                ->get()
                ->map(fn($p) => [
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'stock' => (int) $p->quantity,
                    'aliases' => $this->generateAliases($p->name), // v6: Support plural/dual forms
                ])
                ->values()
                ->toArray();

            // Prepare store context
            $storeContext = [
                'name' => $store->name,
                'working_hours' => $store->aiSetting->store_description ?? '9am - 10pm',
                'delivery_time' => 'نفس اليوم',
                'delivery_cost' => 5000, // Default, can be customized
                'return_policy' => $store->aiSetting->store_policies ?? 'استرجاع خلال 7 أيام',
                'products' => $products,
            ];

            // Note: lead_info is NOT sent - API fetches it from Laravel API
            // API v3 expects 'user_id' not 'lead_id'
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v3/session/create", [
                    'store_id' => (string) $storeId,
                    'user_id' => (string) $leadId,
                    'store_context' => $storeContext,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Cache session ID for this lead
                $cacheKey = "invengpt_session_{$storeId}_{$leadId}";
                $expiresInMinutes = $data['expires_in_minutes'] ?? 30; // v6: Default 30 minutes
                Cache::put($cacheKey, $data['session_id'], now()->addMinutes($expiresInMinutes));

                Log::info('InvenGPT: Session created', [
                    'store_id' => $storeId,
                    'lead_id' => $leadId,
                    'session_id' => $data['session_id'],
                    'is_existing' => $data['is_existing'] ?? false,
                ]);

                return $data;
            }

            Log::error('InvenGPT: Session creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('InvenGPT: Session creation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Send a message to existing session
     *
     * @param string $sessionId
     * @param string $message
     * @return array ['reply' => string, 'action_required' => string|null, 'cached' => bool, '_metadata' => array]
     */
    public function sendMessage(string $sessionId, string $message): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v3/chat", [
                    'session_id' => $sessionId,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('InvenGPT: Message sent', [
                    'session_id' => substr($sessionId, 0, 8) . '...',
                    'message_length' => mb_strlen($message),
                    'reply_length' => mb_strlen($data['reply'] ?? ''),
                    'action_required' => $data['action_required'] ?? null,
                    'cached' => $data['cached'] ?? false,
                    'fast_reply' => $data['fast_reply'] ?? false,
                    'intent' => $data['_metadata']['intent'] ?? null,
                ]);

                return $data;
            }

            Log::error('InvenGPT: Message send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'reply' => 'عذراً، حدث خطأ. سيتواصل معك أحد موظفينا قريباً.',
                'action_required' => 'human_agent_needed',
                'cached' => false,
            ];

        } catch (\Exception $e) {
            Log::error('InvenGPT: Message send exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'reply' => 'عذراً، حدث خطأ في الاتصال. يرجى المحاولة لاحقاً.',
                'action_required' => 'human_agent_needed',
                'cached' => false,
            ];
        }
    }

    /**
     * Get or create session for a lead
     *
     * @param int $storeId
     * @param int $leadId
     * @return string|null Session ID
     */
    public function getOrCreateSession(int $storeId, int $leadId): ?string
    {
        $cacheKey = "invengpt_session_{$storeId}_{$leadId}";

        $sessionId = Cache::get($cacheKey);

        if (!$sessionId) {
            $result = $this->createSession($storeId, $leadId);
            $sessionId = $result['session_id'] ?? null;
        }

        return $sessionId;
    }

    /**
     * Generate Arabic aliases for a product name (v6 feature)
     * Handles dual and plural forms
     */
    protected function generateAliases(string $name): array
    {
        $aliases = [];

        // Remove common prefixes/suffixes
        $cleanName = trim($name);

        // Add dual form (ين)
        if (!str_ends_with($cleanName, 'ين')) {
            $aliases[] = $cleanName . 'ين';
        }

        // Add plural form (ات)
        if (!str_ends_with($cleanName, 'ات')) {
            $aliases[] = $cleanName . 'ات';
        }

        return array_unique($aliases);
    }

    /**
     * End a chat session
     *
     * @param string $sessionId
     * @return bool
     */
    public function endSession(string $sessionId): bool
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/api/v3/session/end", [
                    'session_id' => $sessionId,
                ]);

            if ($response->successful()) {
                Log::info('InvenGPT: Session ended', [
                    'session_id' => substr($sessionId, 0, 8) . '...',
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('InvenGPT: End session exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check API health
     *
     * @return bool
     */
    public function health(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            if ($response->successful()) {
                $data = $response->json();
                return ($data['status'] ?? '') === 'healthy' &&
                       ($data['model_loaded'] ?? false) === true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('InvenGPT: Health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process message using webhook (auto-creates session if needed)
     *
     * @param int $storeId
     * @param int $leadId
     * @param string $message
     * @param string $platform
     * @return array
     */
    public function webhook(int $storeId, int $leadId, string $message, string $platform = 'facebook'): array
    {
        try {
            $store = User::with('aiSetting')->find($storeId);
            $lead = Lead::find($leadId);

            if (!$store || !$lead) {
                return [
                    'reply' => 'عذراً، حدث خطأ.',
                    'action_required' => 'human_agent_needed',
                ];
            }

            $products = Product::where('user_id', $storeId)
                ->where('is_active', true)
                ->where('quantity', '>', 0)
                ->limit(20)
                ->get()
                ->map(fn($p) => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'stock' => (int) $p->quantity,
                ])
                ->values()
                ->toArray();

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v3/webhook", [
                    'store_id' => (string) $storeId,
                    'lead_id' => (string) $leadId,
                    'message' => $message,
                    'platform' => $platform,
                    'store_context' => [
                        'name' => $store->name,
                        'products' => $products,
                    ],
                    'lead_info' => [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'phone' => $lead->phone,
                        'city' => $lead->city,
                        'status' => $lead->status,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Cache new session if created
                if ($data['is_new_session'] ?? false) {
                    $cacheKey = "invengpt_session_{$storeId}_{$leadId}";
                    Cache::put($cacheKey, $data['session_id'], now()->addDay());
                }

                return $data;
            }

            return [
                'reply' => 'عذراً، حدث خطأ.',
                'action_required' => 'human_agent_needed',
            ];

        } catch (\Exception $e) {
            Log::error('InvenGPT: Webhook exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'reply' => 'عذراً، حدث خطأ في الاتصال.',
                'action_required' => 'human_agent_needed',
            ];
        }
    }
}
