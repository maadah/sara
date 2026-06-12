<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\ProductController;
use App\Http\Controllers\Customer\CategoryController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ReportController;
use App\Http\Controllers\Customer\MessageController;
use App\Http\Controllers\Customer\MessagesController;
use App\Http\Controllers\Customer\POSController;
use App\Http\Controllers\Customer\SaleController;
use App\Http\Controllers\Customer\SubscriptionController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\SocialAccountController;
use App\Http\Controllers\Customer\AiSettingsController;
use App\Http\Controllers\Customer\CustomerProfileController;
use App\Http\Controllers\Customer\LeadController;
use App\Http\Controllers\Customer\OnlineOrderController;
use App\Http\Controllers\Customer\ReportsController;
use App\Http\Controllers\Customer\CommentReplyController;
use App\Http\Controllers\Customer\CompetitorController;
use App\Http\Controllers\Customer\InventoryController;
use App\Http\Controllers\Customer\TeamController;
use App\Http\Controllers\Customer\AnalyticsController;
use App\Http\Controllers\Customer\ServiceController;
use App\Http\Controllers\Customer\BroadcastController;
use App\Http\Controllers\Customer\SettingsController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Http\Controllers\Proxy\ProxyAuthController;
use App\Http\Controllers\Proxy\ProxyApiController;
use App\Http\Controllers\Proxy\ProxyAdminController;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'sendContact'])->name('contact.send');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

// Proxy OAuth callback (when this instance runs as proxy client)
// No auth middleware — HMAC signature is the authentication.
// MUST be registered BEFORE the {provider} wildcard route below!
Route::get('/auth/proxy/callback', [SocialAuthController::class, 'proxyCallback'])
    ->name('social.proxy.callback');

// Social Auth Routes (public)
// Instagram Direct (Instagram Login for Business) — proxy mode only.
// MUST be registered BEFORE the {provider} wildcard route below!
Route::get('/auth/instagram-direct/redirect', [SocialAuthController::class, 'instagramDirectRedirect'])
    ->name('social.instagram-direct.redirect');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

// Meta (Facebook/Instagram) Webhook Routes
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');

// ─── Proxy System ───────────────────────────────────────────────────

// Proxy OAuth flow (public — external platforms redirect users here)
Route::get('/proxy/auth/start', [ProxyAuthController::class, 'start'])->name('proxy.auth.start');
Route::get('/proxy/auth/callback', [ProxyAuthController::class, 'callback'])->name('proxy.auth.callback');
Route::get('/proxy/oauth-return', [ProxyAuthController::class, 'oauthReturn'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('proxy.oauth.return');

// Proxy API (HMAC-authenticated, CSRF excluded in bootstrap/app.php)
Route::prefix('proxy/api')->name('proxy.api.')->group(function () {
    Route::post('/send-message',     [ProxyApiController::class, 'sendMessage'])->name('send-message');
    Route::post('/send-image',       [ProxyApiController::class, 'sendImage'])->name('send-image');
    Route::post('/reply-comment',    [ProxyApiController::class, 'replyComment'])->name('reply-comment');
    Route::post('/participant-info', [ProxyApiController::class, 'participantInfo'])->name('participant-info');
    Route::post('/conversations',    [ProxyApiController::class, 'conversations'])->name('conversations');
    Route::get('/pages',             [ProxyApiController::class, 'listPages'])->name('pages');
});

// Proxy Admin (password-protected)
Route::prefix('proxy/admin')->name('proxy.admin.')->group(function () {
    Route::get('/login',  [ProxyAdminController::class, 'loginForm'])->name('login');
    Route::post('/login', [ProxyAdminController::class, 'login'])->name('login.post');
    Route::get('/logout', [ProxyAdminController::class, 'logout'])->name('logout');

    Route::middleware('proxy.admin')->group(function () {
        Route::get('/',              [ProxyAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/docs',          [ProxyAdminController::class, 'docs'])->name('docs');
        Route::get('/create',        [ProxyAdminController::class, 'create'])->name('create');
        Route::post('/',             [ProxyAdminController::class, 'store'])->name('store');
        Route::get('/{platform}',    [ProxyAdminController::class, 'show'])->name('show');
        Route::get('/{platform}/edit', [ProxyAdminController::class, 'edit'])->name('edit');
        Route::put('/{platform}',    [ProxyAdminController::class, 'update'])->name('update');
        Route::post('/{platform}/regenerate-keys', [ProxyAdminController::class, 'regenerateKeys'])->name('regenerate');
        Route::post('/{platform}/toggle',          [ProxyAdminController::class, 'toggleActive'])->name('toggle');
    });
});

// Guest routes (Auth)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-analytics', [AdminDashboardController::class, 'aiAnalytics'])->name('ai-analytics');
    Route::get('/ai-management', [AdminDashboardController::class, 'aiManagement'])->name('ai-management');

    // AI Settings (Developer)
    Route::get('/ai-settings', [\App\Http\Controllers\Admin\AiSettingsController::class, 'index'])->name('ai-settings.index');
    Route::put('/ai-settings', [\App\Http\Controllers\Admin\AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('/ai-settings/test', [\App\Http\Controllers\Admin\AiSettingsController::class, 'testConnection'])->name('ai-settings.test');
    Route::get('/ai-settings/usage', [\App\Http\Controllers\Admin\AiSettingsController::class, 'getUsageStats'])->name('ai-settings.usage');
    Route::post('/ai-settings/reset', [\App\Http\Controllers\Admin\AiSettingsController::class, 'resetToDefault'])->name('ai-settings.reset');
    Route::get('/ai-settings/store/{store}', [\App\Http\Controllers\Admin\AiSettingsController::class, 'showStore'])->name('ai-settings.store');
    Route::put('/ai-settings/store/{store}', [\App\Http\Controllers\Admin\AiSettingsController::class, 'updateStore'])->name('ai-settings.store.update');

    // AI Management Actions
    Route::put('/knowledge-base/{knowledgeBase}', [AdminDashboardController::class, 'updateKnowledgeBase'])->name('knowledge-base.update');
    Route::delete('/knowledge-base/{knowledgeBase}', [AdminDashboardController::class, 'deleteKnowledgeBase'])->name('knowledge-base.delete');
    Route::post('/knowledge-base', [AdminDashboardController::class, 'createKnowledgeBase'])->name('knowledge-base.create');
    Route::post('/knowledge-base/bulk-action', [AdminDashboardController::class, 'bulkKnowledgeBaseAction'])->name('knowledge-base.bulk-action');

    Route::put('/fast-reply/{fastReply}', [AdminDashboardController::class, 'updateFastReply'])->name('fast-reply.update');
    Route::delete('/fast-reply/{fastReply}', [AdminDashboardController::class, 'deleteFastReply'])->name('fast-reply.delete');
    Route::post('/fast-reply', [AdminDashboardController::class, 'createFastReply'])->name('fast-reply.create');

    Route::post('/unanswered-question/{question}/answer', [AdminDashboardController::class, 'answerQuestion'])->name('unanswered-question.answer');

    Route::get('/merchants', [AdminDashboardController::class, 'merchants'])->name('merchants');
    Route::get('/merchants/{user}', [AdminDashboardController::class, 'showMerchant'])->name('merchants.show');
    Route::get('/merchants/{user}/edit', [AdminDashboardController::class, 'editMerchant'])->name('merchants.edit');
    Route::put('/merchants/{user}', [AdminDashboardController::class, 'updateMerchant'])->name('merchants.update');
    Route::get('/subscriptions', [AdminDashboardController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/pending-requests', [AdminDashboardController::class, 'pendingRequests'])->name('pending-requests');
    Route::post('/users/{user}/approve', [AdminDashboardController::class, 'approveUser'])->name('users.approve');
    Route::post('/users/{user}/reject', [AdminDashboardController::class, 'rejectUser'])->name('users.reject');
});

// Customer routes
Route::middleware(['auth', 'customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/pending', [CustomerDashboardController::class, 'pending'])->name('pending');
    Route::get('/expired', [CustomerDashboardController::class, 'expired'])->name('expired');

    // Routes that require approval
    Route::middleware('approved')->group(function () {
        Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [CustomerDashboardController::class, 'profile'])->name('profile');
        Route::put('/profile', [CustomerDashboardController::class, 'updateProfile'])->name('profile.update');

        // Categories
        Route::resource('categories', CategoryController::class);

        // Products (Inventory)
        Route::resource('products', ProductController::class);
        Route::delete('/products/images/{image}', [ProductController::class, 'deleteImage'])->name('products.images.delete');

        // Orders
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

        // Reports (Updated to use ReportsController)
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/sales', [ReportsController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/products', [ReportsController::class, 'products'])->name('reports.products');
        Route::get('/reports/leads', [ReportsController::class, 'leads'])->name('reports.leads');
        Route::get('/reports/messages', [ReportsController::class, 'messages'])->name('reports.messages');
        Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');

        // Messages
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');

        // Inbox (Real-time Messages from Facebook/Instagram)
        Route::get('/inbox', [MessagesController::class, 'index'])->name('inbox.index');
        Route::get('/inbox/conversation/{conversation}', [MessagesController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/conversation/{conversation}/send', [MessagesController::class, 'sendMessage'])->name('inbox.send');
        Route::post('/inbox/conversation/{conversation}/send-image', [MessagesController::class, 'sendImage'])->name('inbox.send-image');
        Route::post('/inbox/conversation/{conversation}/read', [MessagesController::class, 'markAsRead'])->name('inbox.read');
        Route::post('/inbox/conversation/{conversation}/archive', [MessagesController::class, 'archive'])->name('inbox.archive');
        Route::post('/inbox/conversation/{conversation}/restore', [MessagesController::class, 'restore'])->name('inbox.restore');
        Route::get('/inbox/conversation/{conversation}/messages', [MessagesController::class, 'getMessages'])->name('inbox.messages');
        Route::post('/inbox/conversation/{conversation}/refresh', [MessagesController::class, 'refreshParticipant'])->name('inbox.refresh');
        Route::post('/inbox/sync', [MessagesController::class, 'sync'])->name('inbox.sync');

        // POS (Point of Sale)
        Route::get('/pos', [POSController::class, 'index'])->name('pos.index');
        Route::post('/pos/search', [POSController::class, 'searchProducts'])->name('pos.search');
        Route::post('/pos/complete', [POSController::class, 'completeSale'])->name('pos.complete');
        Route::get('/pos/sale/{sale}', [POSController::class, 'getSale'])->name('pos.sale');
        Route::get('/pos/invoice/{sale}', [POSController::class, 'printInvoice'])->name('pos.invoice');

        // Sales (المبيعات)
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

        // Subscription (اشتراكي)
        Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription/change', [SubscriptionController::class, 'changePlan'])->name('subscription.change');

        // Notifications (الإشعارات)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unreadCount');
        Route::get('/notifications/recent', [NotificationController::class, 'getRecent'])->name('notifications.recent');

        // Social Accounts Management (الحسابات المرتبطة)
        Route::get('/social-accounts', [SocialAccountController::class, 'index'])->name('social-accounts.index');
        Route::post('/social-accounts/check-permissions', [SocialAccountController::class, 'checkPermissions'])->name('social-accounts.check-permissions');
        Route::post('/social-accounts/diagnose', [SocialAccountController::class, 'diagnose'])->name('social-accounts.diagnose');
        Route::delete('/social-accounts/{provider}/unlink', [SocialAuthController::class, 'unlink'])->name('social-accounts.unlink');
        Route::get('/social-accounts/relink-confirm', [SocialAuthController::class, 'relinkConfirm'])->name('social-accounts.relink-confirm');
        Route::post('/social-accounts/relink', [SocialAuthController::class, 'relinkConfirmed'])->name('social-accounts.relink');
        Route::post('/social-accounts/relink-cancel', [SocialAuthController::class, 'relinkCancel'])->name('social-accounts.relink-cancel');
        Route::get('/social-accounts/meta-posts', [\App\Http\Controllers\Customer\MetaPostController::class, 'getAccountPosts'])->name('social-accounts.meta-posts');
        Route::post('/social-accounts/resolve-url', [\App\Http\Controllers\Customer\MetaPostController::class, 'resolveUrl'])->name('social-accounts.resolve-url');

        // Comment Replies System (نظام الردود على التعليقات)
        Route::get('/comment-replies', [CommentReplyController::class, 'index'])->name('comment-replies.index');
        Route::delete('/comment-replies/{interaction}', [CommentReplyController::class, 'destroy'])->name('comment-replies.destroy');
        Route::post('/comment-replies/cleanup', [CommentReplyController::class, 'cleanup'])->name('comment-replies.cleanup');

        // AI Settings (إعدادات الذكاء الاصطناعي)
        Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
        Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
        Route::post('/ai-settings/test-connection', [AiSettingsController::class, 'testConnection'])->name('ai-settings.test');
        Route::get('/ai-settings/models', [AiSettingsController::class, 'getModels'])->name('ai-settings.models');
        Route::post('/ai-settings/reset', [AiSettingsController::class, 'resetToDefault'])->name('ai-settings.reset');
        Route::post('/ai-settings/sync-assistant', [AiSettingsController::class, 'syncAssistant'])->name('ai-settings.sync-assistant');
        Route::delete('/ai-settings/delete-assistant', [AiSettingsController::class, 'deleteAssistant'])->name('ai-settings.delete-assistant');

        // AI Helper System (نظام مساعد الذكاء الاصطناعي)
        Route::prefix('ai-helper')->name('ai-helper.')->group(function () {
            // Dashboard
            Route::get('/', [\App\Http\Controllers\Admin\AiHelperController::class, 'index'])->name('index');
            Route::get('/metrics', [\App\Http\Controllers\Admin\AiHelperController::class, 'getMetrics'])->name('metrics');
            Route::get('/notification-count', [\App\Http\Controllers\Admin\AiHelperController::class, 'getNotificationCount'])->name('notification-count');

            // Knowledge Base
            Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'edit'])->name('edit');
                Route::put('/{id}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'update'])->name('update');
                Route::delete('/{id}', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/toggle-status', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'toggleStatus'])->name('toggle-status');
                Route::post('/search-similar', [\App\Http\Controllers\Admin\KnowledgeBaseController::class, 'searchSimilar'])->name('search-similar');
            });

            // Unanswered Questions
            Route::prefix('unanswered')->name('unanswered.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'index'])->name('index');
                Route::get('/{id}', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'show'])->name('show');
                Route::post('/{id}/answer', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'answer'])->name('answer');
                Route::post('/{id}/ignore', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'ignore'])->name('ignore');
                Route::post('/{id}/toggle-urgent', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'toggleUrgent'])->name('toggle-urgent');
                Route::post('/{id}/reply-customer', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'replyToCustomer'])->name('reply-customer');
                Route::get('/count/unreviewed', [\App\Http\Controllers\Admin\UnansweredQuestionsController::class, 'getUnreviewedCount'])->name('count');
            });

            // Fast Replies
            Route::prefix('fast-replies')->name('fast-replies.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\FastRepliesController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Admin\FastRepliesController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Admin\FastRepliesController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [\App\Http\Controllers\Admin\FastRepliesController::class, 'edit'])->name('edit');
                Route::put('/{id}', [\App\Http\Controllers\Admin\FastRepliesController::class, 'update'])->name('update');
                Route::delete('/{id}', [\App\Http\Controllers\Admin\FastRepliesController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/toggle-status', [\App\Http\Controllers\Admin\FastRepliesController::class, 'toggleStatus'])->name('toggle-status');
            });
        });

        // Leads Management (إدارة العملاء المحتملين)
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::post('/leads/{lead}/interest', [LeadController::class, 'addInterest'])->name('leads.interest.add');
        Route::delete('/leads/{lead}/interest', [LeadController::class, 'removeInterest'])->name('leads.interest.remove');
        Route::get('/leads/{lead}/conversation', [LeadController::class, 'conversation'])->name('leads.conversation');

        // Customer Profiles — AI-collected demographic data
        Route::get('/customer-profiles', [CustomerProfileController::class, 'index'])->name('customer-profiles.index');
        Route::get('/customer-profiles/{customerProfile}', [CustomerProfileController::class, 'show'])->name('customer-profiles.show');
        Route::put('/customer-profiles/{customerProfile}', [CustomerProfileController::class, 'update'])->name('customer-profiles.update');

        // Online Orders (الطلبات عبر الإنترنت)
        Route::get('/online-orders', [OnlineOrderController::class, 'index'])->name('online-orders.index');
        Route::get('/online-orders/export', [OnlineOrderController::class, 'export'])->name('online-orders.export');
        Route::get('/online-orders/create', [OnlineOrderController::class, 'create'])->name('online-orders.create');
        Route::post('/online-orders', [OnlineOrderController::class, 'store'])->name('online-orders.store');
        Route::get('/online-orders/{onlineOrder}', [OnlineOrderController::class, 'show'])->name('online-orders.show');
        Route::get('/online-orders/{onlineOrder}/edit', [OnlineOrderController::class, 'edit'])->name('online-orders.edit');
        Route::put('/online-orders/{onlineOrder}', [OnlineOrderController::class, 'update'])->name('online-orders.update');
        Route::delete('/online-orders/{onlineOrder}', [OnlineOrderController::class, 'destroy'])->name('online-orders.destroy');
        Route::post('/online-orders/{onlineOrder}/status', [OnlineOrderController::class, 'updateStatus'])->name('online-orders.status');
        Route::get('/online-orders/{onlineOrder}/conversation', [OnlineOrderController::class, 'conversation'])->name('online-orders.conversation');
        Route::get('/online-orders/{onlineOrder}/print', [OnlineOrderController::class, 'print'])->name('online-orders.print');

        // Reports & Analytics (التقارير والإحصائيات)
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/sales', [ReportsController::class, 'sales'])->name('analytics.sales');
        Route::get('/analytics/leads', [ReportsController::class, 'leads'])->name('analytics.leads');
        Route::get('/analytics/messages', [ReportsController::class, 'messages'])->name('analytics.messages');
        Route::get('/analytics/export', [ReportsController::class, 'export'])->name('analytics.export');

        // Competitor Analysis (تحليل المنافسين)
        Route::get('/competitors', [CompetitorController::class, 'index'])->name('competitors.index');
        Route::post('/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
        Route::get('/competitors/{competitor}', [CompetitorController::class, 'show'])->name('competitors.show');
        Route::put('/competitors/{competitor}', [CompetitorController::class, 'update'])->name('competitors.update');
        Route::delete('/competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('competitors.destroy');

        // Inventory Management (إدارة المخزون)
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('/inventory/products/{product}/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');

        // Team Management (إدارة الفريق)
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::put('/team/{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        // Services (الخدمات)
        Route::resource('services', ServiceController::class);

        // Broadcasts (حملات البث)
        Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
        Route::get('/broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
        Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
        Route::get('/broadcasts/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
        Route::delete('/broadcasts/{broadcast}', [BroadcastController::class, 'destroy'])->name('broadcasts.destroy');

        // Settings (الإعدادات)

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});


