<?php

namespace Tests\Feature;

use App\Enums\ConversationState;
use App\Enums\Intent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\CustomerProfile;
use App\Models\MissedIntent;
use App\Services\Chat\ChatOrchestrator;
use App\Services\Chat\ConversationEngine;
use App\Services\Chat\CustomerProfileManager;
use App\Services\Chat\EntityExtractor;
use App\Services\Chat\IntentClassifier;
use App\Services\Chat\SessionManager;
use App\Services\Chat\StateMachine;
use App\Services\Chat\Tools\CartTool;
use App\Services\Chat\Tools\ProductSearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private ChatOrchestrator $orchestrator;
    private $mockEngine;
    private $mockClassifier;
    private $mockExtractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClassifier = Mockery::mock(IntentClassifier::class);
        $this->mockExtractor  = Mockery::mock(EntityExtractor::class);
        $this->mockEngine     = Mockery::mock(ConversationEngine::class);

        $this->orchestrator = new ChatOrchestrator(
            sessionManager: app(SessionManager::class),
            intentClassifier: $this->mockClassifier,
            entityExtractor: $this->mockExtractor,
            engine: $this->mockEngine,
            stateMachine: app(StateMachine::class),
            profileManager: app(CustomerProfileManager::class),
            productSearch: app(ProductSearchTool::class),
            cartTool: app(CartTool::class),
        );
    }

    /** @test */
    public function it_creates_a_session_on_first_message(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $this->mockGreetingFlow();

        $result = $this->orchestrator->processMessage(
            storeId: $store->id,
            leadId: $lead->id,
            message: 'مرحبا',
            channel: 'web',
        );

        $this->assertArrayHasKey('reply', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertNotEmpty($result['reply']);
        $this->assertDatabaseHas('chat_sessions', [
            'store_id' => $store->id,
            'lead_id'  => $lead->id,
        ]);
    }

    /** @test */
    public function it_reuses_existing_session(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $this->mockGreetingFlow();

        $result1 = $this->orchestrator->processMessage($store->id, $lead->id, 'مرحبا');

        $this->mockBrowseFlow();

        $result2 = $this->orchestrator->processMessage($store->id, $lead->id, 'شنو عدكم');

        $this->assertEquals($result1['session_id'], $result2['session_id']);
    }

    /** @test */
    public function it_saves_user_and_assistant_messages(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $this->mockGreetingFlow();

        $result = $this->orchestrator->processMessage($store->id, $lead->id, 'مرحبا');

        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $result['session_id'],
            'role'       => 'user',
            'content'    => 'مرحبا',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $result['session_id'],
            'role'       => 'assistant',
        ]);
    }

    /** @test */
    public function it_logs_missed_intents(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $this->mockClassifier
            ->shouldReceive('classify')
            ->once()
            ->andReturn(['intent' => Intent::UNKNOWN, 'confidence' => 0.2]);

        $this->mockExtractor
            ->shouldReceive('extract')
            ->once()
            ->andReturn([]);

        $this->mockEngine
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn([
                'reply'      => 'ما فهمت سؤالك',
                'images'     => [],
                'products'   => [],
                'tool_calls' => [],
                'tokens_used' => 50,
            ]);

        $this->orchestrator->processMessage($store->id, $lead->id, 'xyz garbled text');

        $this->assertDatabaseHas('missed_intents', [
            'store_id'    => $store->id,
            'raw_message' => 'xyz garbled text',
        ]);
    }

    /** @test */
    public function it_merges_customer_entities_into_session(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $this->mockClassifier
            ->shouldReceive('classify')
            ->once()
            ->andReturn(['intent' => Intent::PROVIDE_NAME, 'confidence' => 0.9]);

        $this->mockExtractor
            ->shouldReceive('extract')
            ->once()
            ->andReturn(['customer_name' => 'أحمد']);

        $this->mockEngine
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn([
                'reply'      => 'أهلاً أحمد! شنو رقمك؟',
                'images'     => [],
                'products'   => [],
                'tool_calls' => [],
                'tokens_used' => 60,
            ]);

        $result = $this->orchestrator->processMessage($store->id, $lead->id, 'اسمي أحمد');
        $session = ChatSession::find($result['session_id']);

        $this->assertEquals('أحمد', $session->customer_data['name']);
    }

    /** @test */
    public function it_returns_actions_for_human_handover(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        // Create a session already in HUMAN_HANDOVER state
        $session = ChatSession::create([
            'store_id'         => $store->id,
            'lead_id'          => $lead->id,
            'channel'          => 'web',
            'state'            => ConversationState::HUMAN_HANDOVER,
            'cart'             => ['items' => [], 'total' => 0, 'grand_total' => 0],
            'customer_data'    => [],
            'history'          => [],
            'meta'             => [],
            'last_activity_at' => now(),
        ]);

        $this->mockClassifier
            ->shouldReceive('classify')
            ->once()
            ->andReturn(['intent' => Intent::OUT_OF_SCOPE, 'confidence' => 0.8]);

        $this->mockExtractor
            ->shouldReceive('extract')
            ->once()
            ->andReturn([]);

        $this->mockEngine
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn([
                'reply'      => 'راح احولك للدعم',
                'images'     => [],
                'products'   => [],
                'tool_calls' => [],
                'tokens_used' => 30,
            ]);

        $result = $this->orchestrator->processMessage($store->id, $lead->id, 'ابي اتكلم مع شخص');

        $this->assertContains('human_handover', $result['actions']);
    }

    /** @test */
    public function it_returns_session_stats(): void
    {
        $store = $this->createStore();
        $lead  = $this->createLead($store->id);

        $session = ChatSession::create([
            'store_id'         => $store->id,
            'lead_id'          => $lead->id,
            'channel'          => 'web',
            'state'            => ConversationState::BROWSING,
            'cart'             => ['items' => [], 'total' => 0, 'grand_total' => 0],
            'customer_data'    => [],
            'history'          => [],
            'meta'             => [],
            'last_activity_at' => now(),
        ]);

        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'user',
            'content'     => 'مرحبا',
            'intent'      => 'greeting',
            'tokens_used' => 0,
            'created_at'  => now(),
        ]);

        ChatMessage::create([
            'session_id'  => $session->id,
            'role'        => 'assistant',
            'content'     => 'أهلاً!',
            'tokens_used' => 100,
            'created_at'  => now(),
        ]);

        $stats = $this->orchestrator->getSessionStats($session->id);

        $this->assertEquals($session->id, $stats['session_id']);
        $this->assertEquals(2, $stats['total_messages']);
        $this->assertEquals(100, $stats['total_tokens']);
        $this->assertEquals('still_browsing', $stats['outcome']);
    }

    /** @test */
    public function the_api_endpoint_returns_422_for_invalid_input(): void
    {
        $response = $this->postJson('/api/v1/chat', []);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'errors']);
    }

    /* ================================================================== */
    /* Helpers                                                             */
    /* ================================================================== */

    private function createStore(): \App\Models\User
    {
        return \App\Models\User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function createLead(int $storeId): \App\Models\Lead
    {
        return \App\Models\Lead::create([
            'user_id'    => $storeId,
            'name'       => 'Test Lead',
            'platform'   => 'web',
            'platform_id' => 'test-' . uniqid(),
        ]);
    }

    private function mockGreetingFlow(): void
    {
        $this->mockClassifier
            ->shouldReceive('classify')
            ->once()
            ->andReturn(['intent' => Intent::GREETING, 'confidence' => 0.95]);

        $this->mockExtractor
            ->shouldReceive('extract')
            ->once()
            ->andReturn([]);

        $this->mockEngine
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn([
                'reply'      => 'هلا بيك! كيف أگدر أساعدك اليوم؟',
                'images'     => [],
                'products'   => [],
                'tool_calls' => [],
                'tokens_used' => 80,
            ]);
    }

    private function mockBrowseFlow(): void
    {
        $this->mockClassifier
            ->shouldReceive('classify')
            ->once()
            ->andReturn(['intent' => Intent::BROWSE_GENERAL, 'confidence' => 0.85]);

        $this->mockExtractor
            ->shouldReceive('extract')
            ->once()
            ->andReturn([]);

        $this->mockEngine
            ->shouldReceive('generateReply')
            ->once()
            ->andReturn([
                'reply'      => 'عدنا أقسام متعددة، شنو يهمك؟',
                'images'     => [],
                'products'   => [],
                'tool_calls' => [],
                'tokens_used' => 90,
            ]);
    }
}
