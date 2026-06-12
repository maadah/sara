<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\GroqChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI Chat Testing API Controller
 *
 * Provides endpoints for testing the AI chat system without Facebook Messenger.
 * Simulates real conversations and validates AI responses automatically.
 */
class AiChatTestController extends Controller
{
    /**
     * Send a test message and get AI response
     *
     * POST /api/ai-test/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $message = $request->message;
        $sessionId = $request->session_id;

        [$conversation, $lead, $testId] = $this->getOrCreateTestEntities($user, $sessionId);

        $aiService = new AiChatService($user);
        $startTime = microtime(true);

        try {
            $response = $aiService->processMessage($conversation, $message);
            $duration = round((microtime(true) - $startTime) * 1000);

            $session = AiChatSession::where('lead_id', $lead->id)
                ->where('updated_at', '>=', now()->subHours(24))
                ->whereNotIn('conversation_state', ['order_completed', 'cancelled'])
                ->first();

            $latestOrder = OnlineOrder::where('conversation_id', $conversation->id)
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'session_id' => $testId,
                'response' => $response,
                'cart' => $session?->cart ?? [],
                'cart_total' => $session?->getCartTotal() ?? 0,
                'customer_data' => $session?->customer_data ?? [],
                'order_created' => $latestOrder ? [
                    'id' => $latestOrder->id,
                    'order_number' => $latestOrder->order_number,
                    'total' => $latestOrder->total,
                    'status' => $latestOrder->status,
                ] : null,
                'duration_ms' => $duration,
            ]);
        } catch (\Exception $e) {
            Log::error('AI Test Chat Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run a complete conversation test scenario
     *
     * POST /api/ai-test/scenario
     */
    public function runScenario(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'scenario' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $scenarioName = $request->scenario;

        $scenarios = $this->getTestScenarios();

        if (!isset($scenarios[$scenarioName])) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown scenario: ' . $scenarioName,
                'available_scenarios' => array_keys($scenarios),
            ], 400);
        }

        $scenario = $scenarios[$scenarioName];
        $results = $this->executeScenario($user, $scenario);

        return response()->json([
            'success' => $results['success'],
            'scenario' => $scenarioName,
            'description' => $scenario['description'],
            'steps' => $results['steps'],
            'final_state' => $results['final_state'],
            'issues' => $results['issues'],
        ]);
    }

    /**
     * Run ALL test scenarios at once
     *
     * POST /api/ai-test/full-test
     */
    public function fullTest(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $scenarios = $this->getTestScenarios();

        $allResults = [];
        $passCount = 0;
        $failCount = 0;
        $allIssues = [];

        foreach ($scenarios as $name => $scenario) {
            $results = $this->executeScenario($user, $scenario);

            $allResults[$name] = [
                'success' => $results['success'],
                'description' => $scenario['description'],
                'step_count' => count($results['steps']),
                'issues' => $results['issues'],
            ];

            if ($results['success']) {
                $passCount++;
            } else {
                $failCount++;
                foreach ($results['issues'] as $issue) {
                    $allIssues[] = "[{$name}] {$issue}";
                }
            }
        }

        return response()->json([
            'summary' => [
                'total_scenarios' => count($scenarios),
                'passed' => $passCount,
                'failed' => $failCount,
                'success_rate' => round(($passCount / count($scenarios)) * 100, 1) . '%',
            ],
            'results' => $allResults,
            'all_issues' => $allIssues,
        ]);
    }

    /**
     * Get list of all available test scenarios
     *
     * GET /api/ai-test/scenarios
     */
    public function listScenarios(): JsonResponse
    {
        $scenarios = $this->getTestScenarios();

        return response()->json([
            'scenarios' => array_map(fn($s) => [
                'description' => $s['description'],
                'steps' => count($s['messages']),
            ], $scenarios),
        ]);
    }

    /**
     * Reset test data
     *
     * POST /api/ai-test/reset
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $deleted = Conversation::where('participant_id', 'like', 'test_%')->delete();
        $deletedLeads = Lead::where('platform_user_id', 'like', 'test_%')->delete();
        $deletedSessions = AiChatSession::where('platform_user_id', 'like', 'test_%')->delete();

        return response()->json([
            'success' => true,
            'deleted' => [
                'conversations' => $deleted,
                'leads' => $deletedLeads,
                'sessions' => $deletedSessions,
            ],
        ]);
    }

    /**
     * Get or create test entities
     */
    protected function getOrCreateTestEntities(User $user, ?string $sessionId): array
    {
        $testId = $sessionId ?? 'test_' . Str::random(16);

        // Get or create a test social account for this user
        $socialAccount = \App\Models\SocialAccount::where('user_id', $user->id)->first();
        if (!$socialAccount) {
            $socialAccount = \App\Models\SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'test',
                'provider_id' => 'test_account_' . $user->id,
                'name' => 'Test Account',
            ]);
        }

        $conversation = Conversation::firstOrCreate([
            'user_id' => $user->id,
            'participant_id' => $testId,
        ], [
            'social_account_id' => $socialAccount->id,
            'platform' => 'test',
            'participant_name' => 'Test Customer ' . substr($testId, 5, 4),
            'ai_enabled' => true,
            'last_message_at' => now(),
        ]);

        $lead = Lead::firstOrCreate([
            'user_id' => $user->id,
            'platform_user_id' => $testId,
        ], [
            'conversation_id' => $conversation->id,
            'source' => 'facebook',
            'status' => 'new',
        ]);

        // Link lead to conversation so ensureLead() finds it
        if (!$conversation->lead_id) {
            $conversation->lead_id = $lead->id;
            $conversation->save();
        }

        return [$conversation, $lead, $testId];
    }

    /**
     * Execute a test scenario
     */
    protected function executeScenario(User $user, array $scenario): array
    {
        $testId = 'test_scenario_' . Str::random(12);
        [$conversation, $lead, $testId] = $this->getOrCreateTestEntities($user, $testId);

        $aiService = new AiChatService($user);
        $steps = [];
        $issues = [];
        $success = true;

        foreach ($scenario['messages'] as $index => $step) {
            $message = $step['message'];
            $expectedChecks = $step['expect'] ?? [];

            try {
                $startTime = microtime(true);
                $response = $aiService->processMessage($conversation, $message);
                $duration = round((microtime(true) - $startTime) * 1000);

                $session = AiChatSession::where('lead_id', $lead->id)
                    ->where('expires_at', '>', now())
                    ->first();

                $stepIssues = $this->validateExpectations($expectedChecks, $session, $response);

                if (!empty($stepIssues)) {
                    $success = false;
                    foreach ($stepIssues as $issue) {
                        $issues[] = "Step " . ($index + 1) . ": {$issue}";
                    }
                }

                $steps[] = [
                    'step' => $index + 1,
                    'message' => $message,
                    'response' => $response,
                    'response_length' => mb_strlen($response ?? ''),
                    'cart_items' => count($session?->cart ?? []),
                    'cart_total' => $session?->getCartTotal() ?? 0,
                    'customer_data' => $session?->customer_data ?? [],
                    'duration_ms' => $duration,
                    'issues' => $stepIssues,
                    'passed' => empty($stepIssues),
                ];

            } catch (\Exception $e) {
                $success = false;
                $issues[] = "Step " . ($index + 1) . ": Exception - " . $e->getMessage();
                $steps[] = [
                    'step' => $index + 1,
                    'message' => $message,
                    'error' => $e->getMessage(),
                    'passed' => false,
                ];
            }
        }

        $finalSession = AiChatSession::where('lead_id', $lead->id)
            ->where('expires_at', '>', now())
            ->first();

        $finalOrder = OnlineOrder::where('conversation_id', $conversation->id)
            ->latest()
            ->first();

        return [
            'success' => $success,
            'steps' => $steps,
            'final_state' => [
                'cart' => $finalSession?->cart ?? [],
                'cart_total' => $finalSession?->getCartTotal() ?? 0,
                'customer_data' => $finalSession?->customer_data ?? [],
                'order' => $finalOrder ? [
                    'id' => $finalOrder->id,
                    'order_number' => $finalOrder->order_number,
                    'total' => $finalOrder->total,
                    'status' => $finalOrder->status,
                    'items_count' => $finalOrder->items()->count(),
                ] : null,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Validate expectations
     */
    protected function validateExpectations(array $checks, ?AiChatSession $session, ?string $response): array
    {
        $issues = [];

        if (isset($checks['has_response']) && $checks['has_response'] && empty($response)) {
            $issues[] = 'Expected response but got none';
        }

        if (isset($checks['response_contains'])) {
            foreach ((array)$checks['response_contains'] as $text) {
                if ($response && mb_stripos($response, $text) === false) {
                    $issues[] = "Response should contain: {$text}";
                }
            }
        }

        if (isset($checks['response_not_contains'])) {
            foreach ((array)$checks['response_not_contains'] as $text) {
                if ($response && mb_stripos($response, $text) !== false) {
                    $issues[] = "Response should NOT contain: {$text}";
                }
            }
        }

        if (isset($checks['cart_not_empty']) && $checks['cart_not_empty']) {
            if (empty($session?->cart)) {
                $issues[] = 'Cart should not be empty';
            }
        }

        if (isset($checks['cart_empty']) && $checks['cart_empty']) {
            if (!empty($session?->cart)) {
                $issues[] = 'Cart should be empty';
            }
        }

        if (isset($checks['cart_item_count'])) {
            $actualCount = count($session?->cart ?? []);
            if ($actualCount !== $checks['cart_item_count']) {
                $issues[] = "Expected {$checks['cart_item_count']} cart items, got {$actualCount}";
            }
        }

        if (isset($checks['cart_total_min'])) {
            $total = $session?->getCartTotal() ?? 0;
            if ($total < $checks['cart_total_min']) {
                $issues[] = "Cart total ({$total}) should be at least {$checks['cart_total_min']}";
            }
        }

        if (isset($checks['has_customer_name']) && $checks['has_customer_name']) {
            if (empty($session?->customer_data['name'])) {
                $issues[] = 'Customer name should be set';
            }
        }

        if (isset($checks['has_customer_phone']) && $checks['has_customer_phone']) {
            if (empty($session?->customer_data['phone'])) {
                $issues[] = 'Customer phone should be set';
            }
        }

        if (isset($checks['has_customer_address']) && $checks['has_customer_address']) {
            if (empty($session?->customer_data['address'])) {
                $issues[] = 'Customer address should be set';
            }
        }

        return $issues;
    }

    /**
     * Get all test scenarios - 50+ scenarios covering all cases
     */
    protected function getTestScenarios(): array
    {
        return [
            // ==================== BASIC FLOWS ====================

            'greeting' => [
                'description' => 'Basic greeting exchange',
                'messages' => [
                    ['message' => 'السلام عليكم', 'expect' => ['has_response' => true]],
                    ['message' => 'شنو عندكم؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'full_order_iraqi' => [
                'description' => 'Complete order flow - Iraqi dialect',
                'messages' => [
                    ['message' => 'السلام عليكم', 'expect' => ['has_response' => true]],
                    ['message' => 'شنو ارخص شي عدكم؟', 'expect' => ['has_response' => true]],
                    ['message' => 'اريد ٢ قطع', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'زيد محمد', 'expect' => ['has_customer_name' => true]],
                    ['message' => '07812345678', 'expect' => ['has_customer_phone' => true]],
                    ['message' => 'بغداد الكرادة', 'expect' => ['has_customer_address' => true]],
                    ['message' => 'تمام اكد الطلب', 'expect' => ['has_response' => true]],
                ],
            ],

            'full_order_egyptian' => [
                'description' => 'Complete order flow - Egyptian dialect',
                'messages' => [
                    ['message' => 'ازيك', 'expect' => ['has_response' => true]],
                    ['message' => 'عايز اشوف المنتجات', 'expect' => ['has_response' => true]],
                    ['message' => 'عايز حاجة رخيصة', 'expect' => ['has_response' => true]],
                    ['message' => 'هاخد ٣ حتت', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'اسمي محمد علي', 'expect' => ['has_customer_name' => true]],
                    ['message' => '01012345678', 'expect' => ['has_customer_phone' => true]],
                    ['message' => 'القاهرة المعادي', 'expect' => ['has_customer_address' => true]],
                    ['message' => 'ماشي خلص', 'expect' => ['has_response' => true]],
                ],
            ],

            'full_order_gulf' => [
                'description' => 'Complete order flow - Gulf dialect',
                'messages' => [
                    ['message' => 'هلا', 'expect' => ['has_response' => true]],
                    ['message' => 'ابغى اشوف شنو عندكم', 'expect' => ['has_response' => true]],
                    ['message' => 'ابغى اثنين', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "سلطان الدوسري\n0501234567\nالرياض العليا", 'expect' => [
                        'has_customer_name' => true,
                        'has_customer_phone' => true,
                        'has_customer_address' => true,
                    ]],
                    ['message' => 'زين كمل', 'expect' => ['has_response' => true]],
                ],
            ],

            'full_order_levantine' => [
                'description' => 'Complete order flow - Levantine dialect',
                'messages' => [
                    ['message' => 'مرحبا كيفك', 'expect' => ['has_response' => true]],
                    ['message' => 'بدي شي منيح', 'expect' => ['has_response' => true]],
                    ['message' => 'بدي ٥ قطع', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "اسمي ليلى\nرقمي 0791234567\nعمان الصويفية", 'expect' => [
                        'has_customer_name' => true,
                        'has_customer_phone' => true,
                        'has_customer_address' => true,
                    ]],
                    ['message' => 'تمام', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== PRODUCT SEARCH ====================

            'search_by_category' => [
                'description' => 'Search products by category',
                'messages' => [
                    ['message' => 'السلام عليكم', 'expect' => ['has_response' => true]],
                    ['message' => 'شنو عندكم اجهزة كهربائية؟', 'expect' => ['has_response' => true]],
                    ['message' => 'شنو ارخص جهاز؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'search_cheapest' => [
                'description' => 'Search for cheapest product',
                'messages' => [
                    ['message' => 'شنو ارخص شي؟', 'expect' => ['has_response' => true]],
                    ['message' => 'طيب شنو اغلى شي؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'product_details' => [
                'description' => 'Ask about product details',
                'messages' => [
                    ['message' => 'عندكم نظارات؟', 'expect' => ['has_response' => true]],
                    ['message' => 'شكد سعرها؟', 'expect' => ['has_response' => true]],
                    ['message' => 'شنو مواصفاتها؟', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== QUANTITY VARIATIONS ====================

            'quantity_arabic_numerals' => [
                'description' => 'Order with Arabic numerals (٥)',
                'messages' => [
                    ['message' => 'اريد جهاز ٥ قطع', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'quantity_english_numerals' => [
                'description' => 'Order with English numerals (5)',
                'messages' => [
                    ['message' => 'اريد جهاز 5 قطع', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'quantity_words' => [
                'description' => 'Order with quantity words',
                'messages' => [
                    ['message' => 'اريد ثلاثة اجهزة', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'quantity_dual' => [
                'description' => 'Order two items (dual form)',
                'messages' => [
                    ['message' => 'اريد جهازين', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            // ==================== CART OPERATIONS ====================

            'add_multiple_products' => [
                'description' => 'Add multiple different products to cart',
                'messages' => [
                    ['message' => 'اريد جهاز ضغط', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'واريد حقيبة كمان', 'expect' => ['cart_item_count' => 2]],
                ],
            ],

            'modify_quantity' => [
                'description' => 'Modify item quantity in cart',
                'messages' => [
                    ['message' => 'اريد جهاز ٣ قطع', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'لا خليها ٥', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'remove_from_cart' => [
                'description' => 'Remove item from cart',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'واريد حقيبة', 'expect' => ['cart_item_count' => 2]],
                    ['message' => 'احذف الحقيبة', 'expect' => ['cart_item_count' => 1]],
                ],
            ],

            'clear_cart' => [
                'description' => 'Clear entire cart',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'الغي الكل', 'expect' => ['cart_empty' => true]],
                ],
            ],

            // ==================== CUSTOMER INFO VARIATIONS ====================

            'info_one_message' => [
                'description' => 'Customer gives all info in one message',
                'messages' => [
                    ['message' => 'اريد جهاز ٢ قطع', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "اسمي احمد محمد\nرقمي 07812345678\nعنواني بغداد", 'expect' => [
                        'has_customer_name' => true,
                        'has_customer_phone' => true,
                        'has_customer_address' => true,
                    ]],
                ],
            ],

            'info_separate_messages' => [
                'description' => 'Customer gives info in separate messages',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'علي حسين', 'expect' => ['has_customer_name' => true]],
                    ['message' => '07734567890', 'expect' => ['has_customer_phone' => true]],
                    ['message' => 'البصرة المعقل', 'expect' => ['has_customer_address' => true]],
                ],
            ],

            // ==================== CONFIRMATION VARIATIONS ====================

            'confirm_with_yes' => [
                'description' => 'Confirm with "نعم"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'نعم', 'expect' => ['has_response' => true]],
                ],
            ],

            'confirm_with_tamam' => [
                'description' => 'Confirm with "تمام"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'تمام', 'expect' => ['has_response' => true]],
                ],
            ],

            'confirm_with_continue' => [
                'description' => 'Confirm with "استمر"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'استمر', 'expect' => ['has_response' => true]],
                ],
            ],

            'confirm_with_mashi' => [
                'description' => 'Confirm with "ماشي"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'ماشي', 'expect' => ['has_response' => true]],
                ],
            ],

            'confirm_with_khalas' => [
                'description' => 'Confirm with "خلاص"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'خلاص', 'expect' => ['has_response' => true]],
                ],
            ],

            'confirm_with_zain' => [
                'description' => 'Confirm with "زين"',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                    ['message' => "احمد\n0781234567\nبغداد", 'expect' => ['has_customer_name' => true]],
                    ['message' => 'زين', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== CANCELLATION ====================

            'cancel_order' => [
                'description' => 'Cancel order before confirmation',
                'messages' => [
                    ['message' => 'اريد جهاز ٢ قطع', 'expect' => ['cart_not_empty' => true]],
                    ['message' => 'لا الغي الطلب', 'expect' => ['cart_empty' => true]],
                ],
            ],

            // ==================== EDGE CASES ====================

            'unavailable_product' => [
                'description' => 'Ask for unavailable product',
                'messages' => [
                    ['message' => 'عندكم سيارات؟', 'expect' => ['has_response' => true, 'cart_empty' => true]],
                ],
            ],

            'gibberish' => [
                'description' => 'Send gibberish/nonsense',
                'messages' => [
                    ['message' => 'اسدفغهج', 'expect' => ['has_response' => true]],
                ],
            ],

            'very_long_message' => [
                'description' => 'Send very long message',
                'messages' => [
                    ['message' => 'مرحبا اريد ان اسأل عن المنتجات المتوفرة عندكم وما هي الاسعار وهل يوجد توصيل وكم تكلفة التوصيل وما هي سياسة الاسترجاع وهل يمكنني الدفع عند الاستلام', 'expect' => ['has_response' => true]],
                ],
            ],

            'emoji_message' => [
                'description' => 'Message with emojis',
                'messages' => [
                    ['message' => 'مرحبا 😊 شنو عندكم؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'mixed_language' => [
                'description' => 'Mixed Arabic/English message',
                'messages' => [
                    ['message' => 'Hello اريد product', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== PLURAL FORMS ====================

            'plural_regular' => [
                'description' => 'Regular plural forms (ات)',
                'messages' => [
                    ['message' => 'اريد نظارات', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'plural_irregular' => [
                'description' => 'Irregular plural forms (جمع تكسير)',
                'messages' => [
                    ['message' => 'اريد حقائب', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'plural_belts' => [
                'description' => 'Irregular plural - belts (احزمة)',
                'messages' => [
                    ['message' => 'اريد احزمة', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            // ==================== STORE INFO ====================

            'ask_delivery' => [
                'description' => 'Ask about delivery',
                'messages' => [
                    ['message' => 'كم سعر التوصيل؟', 'expect' => ['has_response' => true]],
                    ['message' => 'وشنو مناطق التوصيل؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'ask_return_policy' => [
                'description' => 'Ask about return policy',
                'messages' => [
                    ['message' => 'شنو سياسة الاسترجاع عندكم؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'ask_payment' => [
                'description' => 'Ask about payment methods',
                'messages' => [
                    ['message' => 'شلون ادفع؟', 'expect' => ['has_response' => true]],
                    ['message' => 'عندكم دفع الكتروني؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'ask_working_hours' => [
                'description' => 'Ask about working hours',
                'messages' => [
                    ['message' => 'شنو ساعات العمل عندكم؟', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== ORDER STATUS ====================

            'check_order_status' => [
                'description' => 'Check existing order status',
                'messages' => [
                    ['message' => 'شنو حالة طلبي؟', 'expect' => ['has_response' => true]],
                ],
            ],

            // ==================== SPELLING VARIATIONS ====================

            'spelling_za_da' => [
                'description' => 'Test ظ/ض spelling variation',
                'messages' => [
                    ['message' => 'اريد نضارات', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'spelling_ta_ha' => [
                'description' => 'Test ة/ه spelling variation',
                'messages' => [
                    ['message' => 'اريد حقيبه', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            // ==================== SPECIFIC PRODUCTS ====================

            'order_device' => [
                'description' => 'Order device (جهاز)',
                'messages' => [
                    ['message' => 'اريد جهاز ضغط', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'order_belt' => [
                'description' => 'Order belt (حزام)',
                'messages' => [
                    ['message' => 'اريد حزام', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'order_bag' => [
                'description' => 'Order bag (حقيبة)',
                'messages' => [
                    ['message' => 'اريد حقيبة لابتوب', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'order_glasses' => [
                'description' => 'Order glasses (نظارة)',
                'messages' => [
                    ['message' => 'اريد نظارة ذكية', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            // ==================== DIALECT SPECIFIC ====================

            'dialect_iraqi_want' => [
                'description' => 'Iraqi - اريد',
                'messages' => [
                    ['message' => 'اريد جهاز', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'dialect_egyptian_want' => [
                'description' => 'Egyptian - عايز',
                'messages' => [
                    ['message' => 'عايز جهاز', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'dialect_gulf_want' => [
                'description' => 'Gulf - ابغى',
                'messages' => [
                    ['message' => 'ابغى جهاز', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'dialect_levantine_want' => [
                'description' => 'Levantine - بدي',
                'messages' => [
                    ['message' => 'بدي جهاز', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            'dialect_saudi_want' => [
                'description' => 'Saudi - ودي',
                'messages' => [
                    ['message' => 'ودي جهاز', 'expect' => ['cart_not_empty' => true]],
                ],
            ],

            // ==================== POLITE REQUESTS ====================

            'polite_mumkin' => [
                'description' => 'Polite request - ممكن',
                'messages' => [
                    ['message' => 'ممكن اشوف جهاز ضغط؟', 'expect' => ['has_response' => true]],
                ],
            ],

            'polite_question' => [
                'description' => 'Question format',
                'messages' => [
                    ['message' => 'عندك حزام للظهر؟', 'expect' => ['has_response' => true]],
                ],
            ],
        ];
    }
}
