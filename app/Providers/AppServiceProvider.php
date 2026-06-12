<?php

namespace App\Providers;

use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\OnlineOrder;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Observers\ProductObserver;
use App\Policies\AiSettingPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\LeadPolicy;
use App\Policies\OnlineOrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SocialAccountPolicy;
use App\Services\AI\IntentAnalyzer;
use App\Services\AI\ResponseGenerator;
use App\Services\Conversation\ConversationManager;
use App\Services\Conversation\StateMachine;
use App\Services\GroqChatServiceV3;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderService;
use App\Services\Orders\ProductService;
use App\Events\CartAbandoned;
use App\Listeners\CartAbandonedListener;
use App\Services\Chat\ChatOrchestrator;
use App\Services\Chat\ConversationEngine;
use App\Services\Chat\CustomerProfileManager;
use App\Services\Chat\EntityExtractor;
use App\Services\Chat\IntentClassifier;
use App\Services\Chat\PromptBuilder;
use App\Services\Chat\ResponseValidator;
use App\Services\Chat\SessionManager;
use App\Services\Chat\StateMachine as ChatStateMachine;
use App\Services\Chat\StoreContextBuilder;
use App\Services\Chat\StorePersonality;
use App\Services\Chat\ToolExecutor;
use App\Services\Chat\Tools\CartTool;
use App\Services\Chat\Tools\CustomerTool;
use App\Services\Chat\Tools\OrderTool;
use App\Services\Chat\Tools\ProductSearchTool;
use App\Services\Chat\Tools\StoreTool;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ============================================================
        // NEW AI Marketing Chatbot (OpenAI-based, multi-tenant)
        // ============================================================
        // Core orchestration
        $this->app->singleton(ChatOrchestrator::class);

        // Session & state management
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(ChatStateMachine::class);

        // AI classification & extraction
        $this->app->singleton(IntentClassifier::class);
        $this->app->singleton(EntityExtractor::class);

        // Conversation engine
        $this->app->singleton(ConversationEngine::class);
        $this->app->singleton(ResponseValidator::class);

        // Prompt building
        $this->app->singleton(PromptBuilder::class);
        $this->app->singleton(StoreContextBuilder::class);
        $this->app->singleton(StorePersonality::class);

        // Customer profiling
        $this->app->singleton(CustomerProfileManager::class);

        // Tools
        $this->app->singleton(ProductSearchTool::class);
        $this->app->singleton(CartTool::class);
        $this->app->singleton(OrderTool::class);
        $this->app->singleton(CustomerTool::class);
        $this->app->singleton(StoreTool::class);
        $this->app->singleton(ToolExecutor::class);

        // ============================================================
        // OLD V3 Chat Services (kept for backward compatibility)
        // ============================================================
        // Register V3 Chat Services as singletons for DI
        // Note: IntentAnalyzer and ResponseGenerator no longer need AiProviderService
        // in constructor - they create it on-demand with User parameter for multi-tenant support
        $this->app->singleton(StateMachine::class);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(CartService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(IntentAnalyzer::class);
        $this->app->singleton(ResponseGenerator::class);
        $this->app->singleton(\App\Services\AI\ChatAgentService::class);

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(CartService::class),
                $app->make(StateMachine::class)
            );
        });

        $this->app->singleton(GroqChatServiceV3::class, function ($app) {
            return new GroqChatServiceV3(
                $app->make(IntentAnalyzer::class),
                $app->make(ResponseGenerator::class),
                $app->make(StateMachine::class),
                $app->make(ConversationManager::class),
                $app->make(CartService::class),
                $app->make(OrderService::class),
                $app->make(ProductService::class),
                $app->make(\App\Services\AI\ChatAgentService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS only in production (not in local dev)
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Register policies
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(OnlineOrder::class, OnlineOrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(AiSetting::class, AiSettingPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);

        // Register observers
        Product::observe(ProductObserver::class);

        // Register events
        Event::listen(CartAbandoned::class, CartAbandonedListener::class);
    }
}
