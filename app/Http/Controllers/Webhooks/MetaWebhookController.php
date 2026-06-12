<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\ConversationUpdated;
use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SocialAccount;
use App\Services\AiChatService;
use App\Services\SocialCommentService;
use App\Services\ProxyWebhookService;
use App\Services\MetaApiService;
use App\Jobs\ProcessMetaMessageJob;
use App\Jobs\ProcessMetaCommentJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Handle webhook verification from Meta.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('Meta webhook verification request', [
            'mode' => $mode,
            'token' => $token,
        ]);

        if ($mode === 'subscribe' && $token === config('services.meta.webhook_verify_token')) {
            Log::info('Meta webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('Meta webhook verification failed');
        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook events from Meta.
     */
    public function handle(Request $request)
    {
        $isProxyForward = $request->hasHeader('X-Proxy-Platform');

        Log::info('Meta webhook handle() invoked', [
            'method'           => $request->method(),
            'ip'               => $request->ip(),
            'content_type'     => $request->header('Content-Type'),
            'x_hub_sig'        => $request->header('X-Hub-Signature-256') ? 'present' : 'missing',
            'is_proxy_forward' => $isProxyForward,
            'raw_body_length'  => strlen($request->getContent()),
        ]);

        // ── Proxy-forwarded webhook verification ─────────────────────────────
        // When this instance receives a webhook forwarded by rihlaa-ai.com,
        // verify the proxy HMAC using our own API key/secret from config.
        if ($isProxyForward) {
            if (!$this->verifyProxySignature($request)) {
                Log::warning('Meta webhook: proxy signature verification failed — ignoring');
                return response('EVENT_RECEIVED', 200);
            }
            Log::info('Meta webhook: proxy signature verified');
        }
        // ── End proxy verification ────────────────────────────────────────────

        $payload = $request->all();

        // Log full payload for debugging
        Log::info('Meta webhook received', [
            'object' => $payload['object'] ?? 'unknown',
            'entry_count' => count($payload['entry'] ?? []),
            'full_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        // Verify this is from a page subscription
        $object = $payload['object'] ?? null;

        if ($object === 'page') {
            Log::info('Routing to handlePageMessages');
            $this->handlePageMessages($payload, $isProxyForward);
        } elseif (in_array($object, ['instagram', 'instagram_account'])) {
            Log::info('Routing to handleInstagramMessages');
            $this->handleInstagramMessages($payload, $isProxyForward);
        } elseif ($object === 'whatsapp_business_account') {
            Log::info('Routing to handleWhatsAppMessages');
            $this->handleWhatsAppMessages($payload);
        } else {
            Log::warning('Unhandled Meta webhook object type', ['object' => $object]);
        }

        // Always respond with 200 OK to acknowledge receipt
        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Handle Facebook Page messages.
     */
    protected function handlePageMessages(array $payload, bool $isProxyForward = false): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $pageId = $entry['id'] ?? null;

            // Skip re-forwarding when this webhook was already forwarded by the proxy server
            if ($pageId && !$isProxyForward) {
                $proxyService = app(ProxyWebhookService::class);
                if ($proxyService->shouldForwardAndForward($pageId, $payload)) {
                    Log::info('Webhook forwarded to proxy platform', ['page_id' => $pageId]);
                    continue;
                }
            }

            $messaging = $entry['messaging'] ?? [];

            foreach ($messaging as $event) {
                $this->processMessagingEvent($event, $pageId, 'facebook');
            }

            // Handle comment changes (field = feed)
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                if ($field === 'feed' && ($value['item'] ?? null) === 'comment') {
                    ProcessMetaCommentJob::dispatch($value, $pageId, 'facebook');
                }
            }
        }
    }

    /**
     * Handle Instagram messages.
     * Instagram webhooks can come in two formats:
     * 1. 'messaging' array (direct messages) - similar to Facebook Messenger
     * 2. 'changes' array (some events) - different structure
     */
    protected function handleInstagramMessages(array $payload, bool $isProxyForward = false): void
    {
        $entries = $payload['entry'] ?? [];

        Log::info('Processing Instagram webhook entries', ['entries_count' => count($entries)]);

        foreach ($entries as $entry) {
            $igAccountId = $entry['id'] ?? null;

            // Log entry structure for debugging
            Log::info('Instagram entry received', [
                'ig_account_id' => $igAccountId,
                'has_messaging' => isset($entry['messaging']),
                'has_changes' => isset($entry['changes']),
                'entry_keys' => array_keys($entry),
            ]);

            // Handle 'messaging' format (direct messages)
            $messaging = $entry['messaging'] ?? [];
            foreach ($messaging as $event) {
                Log::info('Processing Instagram messaging event', ['event_keys' => array_keys($event)]);
                $this->processMessagingEvent($event, $igAccountId, 'instagram');
            }

            // Handle 'changes' format (some Instagram events use this structure)
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                Log::info('Instagram change event', ['field' => $field, 'value_keys' => array_keys($value)]);

                // Handle messages from 'changes' format
                if ($field === 'messages' && isset($value['message'])) {
                    // Convert to messaging event format
                    $event = [
                        'sender' => ['id' => $value['from']['id'] ?? $value['sender']['id'] ?? null],
                        'recipient' => ['id' => $igAccountId],
                        'timestamp' => $value['timestamp'] ?? (time() * 1000),
                        'message' => $value['message'],
                    ];
                    Log::info('Converted Instagram change to messaging event', ['event' => $event]);
                    $this->processMessagingEvent($event, $igAccountId, 'instagram');
                }

                // Handle Instagram comments (field = comments)
                if ($field === 'comments') {
                    ProcessMetaCommentJob::dispatch($value, $igAccountId, 'instagram');
                }
            }
        }
    }

    /**
     * Handle WhatsApp Business Account messages.
     * WhatsApp Cloud API webhook format:
     *   entry[].changes[].value.messages[] — incoming messages
     *   entry[].changes[].value.metadata.phone_number_id — our phone number
     */
    protected function handleWhatsAppMessages(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        Log::info('Processing WhatsApp webhook entries', ['entries_count' => count($entries)]);

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                if ($field !== 'messages') {
                    Log::info('WhatsApp change event (non-messages)', ['field' => $field]);
                    continue;
                }

                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                // Build a quick lookup: wa_id → contact name
                $contactNames = [];
                foreach ($contacts as $c) {
                    $waId = $c['wa_id'] ?? null;
                    $name = $c['profile']['name'] ?? null;
                    if ($waId && $name) {
                        $contactNames[$waId] = $name;
                    }
                }

                foreach ($messages as $msg) {
                    $from = $msg['from'] ?? null;         // sender phone number (wa_id)
                    $msgId = $msg['id'] ?? null;
                    $msgType = $msg['type'] ?? 'text';
                    $timestamp = $msg['timestamp'] ?? null;
                    $textBody = $msg['text']['body'] ?? null;

                    if (!$from || !$phoneNumberId) {
                        continue;
                    }

                    // Convert to the same event format used by processMessagingEvent
                    $event = [
                        'sender' => ['id' => $from],
                        'recipient' => ['id' => $phoneNumberId],
                        'timestamp' => $timestamp ? ($timestamp * 1000) : (time() * 1000),
                        'message' => [
                            'mid' => $msgId,
                            'text' => $textBody,
                        ],
                    ];

                    // Handle non-text messages
                    if ($msgType !== 'text') {
                        $attachmentUrl = null;
                        if (in_array($msgType, ['image', 'video', 'audio', 'document'])) {
                            $mediaId = $msg[$msgType]['id'] ?? null;
                            $attachmentUrl = $mediaId; // Will need Graph API fetch for actual URL
                        }
                        $event['message']['attachments'] = [
                            [
                                'type' => $msgType === 'document' ? 'file' : $msgType,
                                'payload' => ['url' => $attachmentUrl],
                            ]
                        ];
                        if (!$textBody) {
                            $event['message']['text'] = $msg[$msgType]['caption'] ?? null;
                        }
                    }

                    // Store contact name for participant info
                    $event['_whatsapp_contact_name'] = $contactNames[$from] ?? null;

                    Log::info('Processing WhatsApp message', [
                        'from' => $from,
                        'phone_number_id' => $phoneNumberId,
                        'type' => $msgType,
                        'text' => mb_substr($textBody ?? '', 0, 100),
                    ]);

                    $this->processMessagingEvent($event, $phoneNumberId, 'whatsapp');
                }

                // Handle status updates (sent, delivered, read)
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    Log::info('WhatsApp status update', [
                        'id' => $status['id'] ?? null,
                        'status' => $status['status'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Dispatch a single messaging event to the Queue.
     */
    protected function processMessagingEvent(array $event, ?string $accountId, string $platform): void
    {
        // Process inline (synchronous) to avoid missed replies when the queue
        // worker is not running.  The webhook handler responds 200 to Meta
        // immediately (before this method is called), so there is no timeout risk.
        $this->processMessagingEventJob($event, $accountId, $platform);
    }


    /**
     * Verify the HMAC signature on a webhook forwarded by rihlaa-ai.com proxy.
     * Uses this instance's own proxy API key/secret from config.
     */
    protected function verifyProxySignature(Request $request): bool
    {
        $platformKey = $request->header('X-Proxy-Platform');
        $signature   = $request->header('X-Proxy-Signature');
        $timestamp   = $request->header('X-Proxy-Timestamp');

        if (!$platformKey || !$signature || !$timestamp) {
            return false;
        }

        // Reject stale forwards (> 5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('Sara webhook: proxy timestamp too old', ['age' => time() - (int) $timestamp]);
            return false;
        }

        // The X-Proxy-Platform header should match our own API key
        $expectedKey = config('services.meta.proxy_api_key');
        if ($expectedKey && $platformKey !== $expectedKey) {
            Log::warning('Sara webhook: proxy platform key mismatch');
            return false;
        }

        $body        = $request->getContent();
        $secret      = config('services.meta.proxy_api_secret');
        if (!$secret) return false; // No secret configured ? accept anyway

        $expectedSig = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        return hash_equals($expectedSig, $signature);
    }

    /**
     * Process a single messaging event (called from Queue Job).
     */
    public function processMessagingEventJob(array $event, ?string $accountId, string $platform): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        $message = $event['message'] ?? null;
        $postback = $event['postback'] ?? null;
        $reaction = $event['reaction'] ?? null;
        $read = $event['read'] ?? null;
        $delivery = $event['delivery'] ?? null;

        Log::info("Processing {$platform} messaging event", [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'account_id' => $accountId,
            'has_message' => !empty($message),
            'message_text' => $message['text'] ?? '[no text]',
        ]);

        if (!$senderId || !$recipientId) {
            Log::warning('Missing sender or recipient ID in messaging event', [
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
            ]);
            return;
        }

        // Find the social account this message is for
        $providerType = match ($platform) {
            'instagram' => 'instagram',
            'whatsapp' => 'whatsapp',
            default => 'facebook_page',
        };

        Log::info("Looking for social account", [
            'provider_type' => $providerType,
            'recipient_id' => $recipientId,
            'account_id' => $accountId,
        ]);

        $socialAccount = SocialAccount::where('provider', $providerType)
            ->where('provider_id', $recipientId)
            ->first();

        if (!$socialAccount) {
            // Maybe the recipient is the page and sender is external user
            $socialAccount = SocialAccount::where('provider', $providerType)
                ->where('provider_id', $accountId)
                ->first();

            if ($socialAccount) {
                Log::info("Found social account via account_id fallback", ['social_account_id' => $socialAccount->id]);
            }
        }

        if (!$socialAccount) {
            Log::warning("Social account not found for {$platform}", [
                'account_id' => $accountId,
                'recipient_id' => $recipientId,
                'provider_type' => $providerType,
            ]);
            return;
        }

        Log::info("Found social account", [
            'social_account_id' => $socialAccount->id,
            'social_account_name' => $socialAccount->name,
            'user_id' => $socialAccount->user_id,
        ]);

        // Handle different event types
        if ($message) {
            Log::info("Routing to handleIncomingMessage for {$platform}");
            $this->handleIncomingMessage($event, $socialAccount, $platform);
        } elseif ($read) {
            $this->handleReadReceipt($event, $socialAccount);
        } elseif ($delivery) {
            $this->handleDeliveryReceipt($event, $socialAccount);
        } elseif ($reaction) {
            $this->handleReaction($event, $socialAccount, $platform);
        } elseif ($postback) {
            // Handle postbacks (button clicks, quick replies with payload)
            Log::info('Postback received', ['postback' => $postback]);
        }
    }

    /**
     * Handle incoming message.
     */
    protected function handleIncomingMessage(array $event, SocialAccount $socialAccount, string $platform): void
    {
        $senderId = $event['sender']['id'];
        $recipientId = $event['recipient']['id'];
        $messageData = $event['message'];
        $timestamp = $event['timestamp'] ?? null;

        // Determine direction - if sender is the page, it's outgoing (echo)
        $isEcho = $messageData['is_echo'] ?? false;
        $direction = $isEcho ? 'outgoing' : 'incoming';

        // Get or create participant ID based on direction
        $participantId = $isEcho ? $recipientId : $senderId;

        // Skip if this is our own message echo (we already saved it when sending)
        if ($isEcho && isset($messageData['app_id'])) {
            Log::info('Skipping echo message from our app');
            return;
        }

        // Check if message already exists
        $externalId = $messageData['mid'] ?? null;
        if ($externalId && Message::where('external_id', $externalId)->exists()) {
            Log::info('Message already exists', ['external_id' => $externalId]);
            return;
        }

        // Find or create conversation
        $conversation = Conversation::firstOrCreate(
            [
                'social_account_id' => $socialAccount->id,
                'participant_id' => $participantId,
                'platform' => $platform,
            ],
            [
                'user_id' => $socialAccount->user_id,
                'participant_name' => null, // Will be updated via Graph API
                'status' => 'active',
            ]
        );

        // Determine message type and content
        $messageType = 'text';
        $content = $messageData['text'] ?? null;
        $attachments = [];

        if (isset($messageData['attachments'])) {
            $attachments = $this->processAttachments($messageData['attachments']);
            if (empty($content)) {
                $messageType = $attachments[0]['type'] ?? 'file';
            }
        }

        if (isset($messageData['sticker_id'])) {
            $messageType = 'sticker';
            $attachments[] = [
                'type' => 'sticker',
                'sticker_id' => $messageData['sticker_id'],
            ];
        }

        // Handle story mentions (Instagram)
        if (isset($messageData['reply_to']['story'])) {
            $messageType = 'story_reply';
        }

        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $direction === 'outgoing' ? $socialAccount->user_id : null,
            'external_id' => $externalId,
            'direction' => $direction,
            'content' => $content,
            'message_type' => $messageType,
            'attachments' => !empty($attachments) ? $attachments : null,
            'status' => 'delivered',
            'is_read' => $direction === 'outgoing',
            'is_from_customer' => $direction === 'incoming',
            'is_ai_generated' => false,
            'meta_data' => $messageData,
            'platform_created_at' => $timestamp ? \Carbon\Carbon::createFromTimestampMs($timestamp) : now(),
        ]);

        Log::info("Message created: {$message->id} for conversation {$conversation->id}");

        // Update conversation
        $conversation->updateWithNewMessage($message);

        // Fetch participant info if we don't have it
        if (!$conversation->participant_name && $direction === 'incoming') {
            // For WhatsApp, use the contact name from the webhook payload
            $whatsappContactName = $event['_whatsapp_contact_name'] ?? null;
            if ($platform === 'whatsapp' && $whatsappContactName) {
                $conversation->update(['participant_name' => $whatsappContactName]);
            } else {
                $this->fetchParticipantInfo($conversation, $socialAccount);
            }
        }

        // Broadcast events
        event(new NewMessageReceived($message));
        event(new ConversationUpdated($conversation->fresh()));

        // Process AI auto-reply for incoming messages
        if ($direction === 'incoming' && $content && $messageType === 'text') {
            Log::info("Triggering AI auto-reply for {$platform}", [
                'conversation_id' => $conversation->id,
                'message_content' => substr($content, 0, 100),
            ]);
            $this->processAiAutoReply($conversation, $message, $socialAccount, $platform);
        } else {
            Log::info("Skipping AI auto-reply", [
                'direction' => $direction,
                'has_content' => !empty($content),
                'message_type' => $messageType,
            ]);
        }
    }

    /**
     * Process AI auto-reply for incoming message.
     */
    protected function processAiAutoReply(Conversation $conversation, Message $message, SocialAccount $socialAccount, string $platform): void
    {
        try {
            $user = $socialAccount->user;

            Log::info("Processing AI auto-reply", [
                'user_id' => $user->id,
                'platform' => $platform,
                'conversation_id' => $conversation->id,
            ]);

            // Check if user has AI settings and AI is enabled
            $aiSettings = $user->aiSetting;
            if (!$aiSettings || !$aiSettings->ai_enabled || !$aiSettings->auto_reply_enabled) {
                Log::info("AI auto-reply disabled for user {$user->id}", [
                    'has_ai_settings' => !empty($aiSettings),
                    'ai_enabled' => $aiSettings->ai_enabled ?? false,
                    'auto_reply_enabled' => $aiSettings->auto_reply_enabled ?? false,
                ]);
                return;
            }

            // Check if conversation has AI enabled (can be disabled per conversation)
            if ($conversation->ai_enabled === false) {
                Log::info("AI disabled for conversation {$conversation->id}");
                return;
            }

            Log::info("AI settings OK, processing message with AI service");

            // ── Comment-Reply Integration (only when FACEBOOK_ENABLE_COMMENTS=true) ────
            $activeInteraction = null;
            if (config('services.meta.enable_comments', false)) {
                $commentService = new SocialCommentService();
                $activeInteraction = $commentService->findActiveInteraction(
                    $conversation->participant_id,
                    $user->id
                );
            }

            if ($activeInteraction) {
                $product = $activeInteraction->product;
                $detailsMessage = $commentService->buildProductDetailsMessage($product);

                Log::info("Comment-reply DM detected — sending product details", [
                    'product_id' => $product->id,
                    'commenter_id' => $conversation->participant_id,
                ]);

                $sent = $this->sendMessageToMeta($socialAccount, $conversation->participant_id, $detailsMessage, $platform);

                if ($sent) {
                    $aiMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'external_id' => $sent['message_id'] ?? null,
                        'direction' => 'outgoing',
                        'content' => $detailsMessage,
                        'message_type' => 'text',
                        'status' => 'sent',
                        'is_read' => true,
                        'is_from_customer' => false,
                        'is_ai_generated' => true,
                        'meta_data' => ['source' => 'comment_reply_system', 'product_id' => $product->id],
                    ]);

                    $conversation->updateWithNewMessage($aiMessage);
                    event(new NewMessageReceived($aiMessage));
                    event(new ConversationUpdated($conversation->fresh()));

                    // Send product images too
                    if ($product->images->count() > 0 && ($aiSettings->send_product_images ?? true)) {
                        $this->sendMultipleProductImages(
                            $conversation,
                            [$product->id],
                            $socialAccount,
                            $platform,
                            $user
                        );
                    }

                    // Mark interaction so we don't re-send next message
                    $commentService->markDmSent($activeInteraction);
                }

                return; // done — skip normal AI flow
            }
            // ── End Comment-Reply Integration ──────────────────────────

            // Process the message with AI (with product image support)
            $aiService = new AiChatService($user);

            // Get AI response with products to show (images handled by GroqChatService)
            $aiResult = $aiService->processMessageWithProducts($conversation, $message->content);
            $aiResponse = $aiResult['reply'] ?? null;
            $productsToShow = $aiResult['products_to_show'] ?? [];

            Log::info("AI service returned", [
                'has_response' => !empty($aiResponse),
                'response_length' => strlen($aiResponse ?? ''),
                'products_count' => count($productsToShow),
            ]);

            // Resolve the message parts (multi-bubble support)
            $aiMessages = $aiResult['messages'] ?? ($aiResponse ? [$aiResponse] : []);

            if (!empty($aiMessages)) {
                Log::info("Sending AI response to {$platform}", [
                    'recipient_id' => $conversation->participant_id,
                    'parts' => count($aiMessages),
                    'first_preview' => substr($aiMessages[0] ?? '', 0, 100),
                ]);

                // Send each part as a separate message with a short natural delay
                $lastSent = null;
                foreach ($aiMessages as $idx => $msgPart) {
                    if ($idx > 0) {
                        usleep(700000); // 700 ms between bubbles — feels human
                    }
                    $sent = $this->sendMessageToMeta(
                        $socialAccount,
                        $conversation->participant_id,
                        $msgPart,
                        $platform
                    );
                    if ($sent) {
                        $lastSent = $sent;
                    }
                    Log::info("sendMessageToMeta part {$idx}", ['sent' => (bool) $sent]);
                }
                $sent = $lastSent;
                $aiResponse = implode("\n", $aiMessages); // full text for DB record

                if ($sent) {
                    // Create the outgoing message record
                    $aiMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'external_id' => $sent['message_id'] ?? null,
                        'direction' => 'outgoing',
                        'content' => $aiResponse,
                        'message_type' => 'text',
                        'status' => 'sent',
                        'is_read' => true,
                        'is_from_customer' => false,
                        'is_ai_generated' => true,
                        'meta_data' => [
                            'ai_model' => $aiSettings->ai_model ?? 'openai',
                        ],
                    ]);

                    // Update conversation
                    $conversation->updateWithNewMessage($aiMessage);

                    // Broadcast events
                    event(new NewMessageReceived($aiMessage));
                    event(new ConversationUpdated($conversation->fresh()));

                    Log::info("AI auto-reply sent for conversation {$conversation->id}");

                    // Send product images ONLY if AI explicitly returned products_to_show
                    // This is controlled by GroqChatService - only for specific product inquiries or image requests
                    if (!empty($productsToShow) && ($aiSettings->send_product_images ?? true)) {
                        $this->sendMultipleProductImages($conversation, $productsToShow, $socialAccount, $platform, $user);
                    }
                }
            } else {
                Log::warning("AI processing returned empty response for conversation {$conversation->id}");
            }
        } catch (\Exception $e) {
            Log::error("AI auto-reply error: " . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send images for multiple products (max 3 products, 1 image each for quick loading)
     */
    protected function sendMultipleProductImages(Conversation $conversation, array $productIds, SocialAccount $socialAccount, string $platform, \App\Models\User $user): void
    {
        try {
            // Limit to max 3 products to avoid spam
            $productIds = array_slice($productIds, 0, 3);

            $products = \App\Models\Product::whereIn('id', $productIds)
                ->where('user_id', $user->id)
                ->with('images')
                ->get();

            if ($products->isEmpty()) {
                return;
            }

            $metaApi = app(MetaApiService::class);
            $recipientId = $conversation->participant_id;
            $pageId = $socialAccount->provider_id;

            foreach ($products as $product) {
                // Get first image only for each product
                $image = $product->images->first();
                if (!$image) {
                    continue;
                }

                $imageUrl = url('storage/' . $image->image_path);

                $data = $metaApi->sendImage(
                    $pageId,
                    $socialAccount->provider_token,
                    $recipientId,
                    $imageUrl,
                    $platform
                );

                if ($data) {
                    // Create the outgoing message record for the image
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'external_id' => $data['message_id'] ?? null,
                        'direction' => 'outgoing',
                        'content' => '',
                        'message_type' => 'image',
                        'status' => 'sent',
                        'is_read' => true,
                        'is_from_customer' => false,
                        'is_ai_generated' => true,
                        'attachments' => [
                            [
                                'type' => 'image',
                                'url' => $imageUrl,
                                'product_name' => $product->name,
                            ]
                        ],
                        'meta_data' => [
                            'image_url' => $imageUrl,
                            'product_id' => $product->id,
                        ],
                    ]);

                    Log::info("Product image sent for {$product->name}");

                    // Small delay between images to avoid rate limiting
                    usleep(300000); // 0.3 second delay
                } else {
                    Log::error("Failed to send product image for {$product->name}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception sending multiple product images: " . $e->getMessage());
        }
    }

    /**
     * Send message to Meta (Facebook/Instagram).
     *
     * Facebook Pages must use /{PAGE_ID}/messages with the page access token.
     * Instagram uses the linked Facebook Page endpoint.
     * WhatsApp uses /{PHONE_NUMBER_ID}/messages with Cloud API format.
     */
    protected function sendMessageToMeta(SocialAccount $socialAccount, string $recipientId, string $text, string $platform): ?array
    {
        try {
            $metaApi = app(MetaApiService::class);
            $pageId = $socialAccount->provider_id;

            // For Instagram DMs, route through the linked Facebook Page endpoint.
            // The stored provider_token IS the Facebook Page access token.
            // Sending via /{fb-page-id}/messages delivers to Instagram DMs through
            // Meta's unified inbox, bypassing the Instagram Messaging API app-capability requirement.
            if ($platform === 'instagram') {
                $fbPageId = $socialAccount->meta_data['facebook_page_id'] ?? null;
                if ($fbPageId) {
                    $pageId = $fbPageId;
                }
            }

            Log::info("Preparing to send message to Meta", [
                'platform' => $platform,
                'page_id' => $pageId,
                'recipient_id' => $recipientId,
                'text_length' => strlen($text),
                'via_proxy' => $metaApi->isProxy(),
            ]);

            $data = $metaApi->sendMessage(
                $pageId,
                $socialAccount->provider_token,
                $recipientId,
                $text,
                $platform
            );

            if ($data) {
                Log::info("Message sent successfully to {$platform}", ['response' => $data]);
            } else {
                Log::error("Failed to send message to {$platform}", [
                    'page_id' => $pageId,
                    'recipient_id' => $recipientId,
                ]);
            }
            return $data;
        } catch (\Exception $e) {
            Log::error("Error sending message to {$platform}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Send product images to the customer.
     */
    protected function sendProductImages(Conversation $conversation, \App\Models\Product $product, SocialAccount $socialAccount, string $platform, \App\Models\User $user): void
    {
        try {
            $images = $product->images;

            if ($images->isEmpty()) {
                Log::info("Product {$product->id} has no images to send");
                return;
            }

            $metaApi = app(MetaApiService::class);
            $recipientId = $conversation->participant_id;
            $pageId = $socialAccount->provider_id;

            // Send each image (max 3 images)
            $sentCount = 0;
            foreach ($images->take(3) as $image) {
                $imageUrl = url('storage/' . $image->image_path);

                $data = $metaApi->sendImage(
                    $pageId,
                    $socialAccount->provider_token,
                    $recipientId,
                    $imageUrl,
                    $platform
                );

                if ($data) {
                    $sentCount++;

                    // Create the outgoing message record for the image
                    $imageMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'external_id' => $data['message_id'] ?? null,
                        'direction' => 'outgoing',
                        'content' => '',
                        'message_type' => 'image',
                        'status' => 'sent',
                        'is_read' => true,
                        'is_from_customer' => false,
                        'is_ai_generated' => true,
                        'attachments' => [
                            [
                                'type' => 'image',
                                'url' => $imageUrl,
                                'product_name' => $product->name,
                            ]
                        ],
                        'meta_data' => [
                            'image_url' => $imageUrl,
                            'product_id' => $product->id,
                        ],
                    ]);

                    Log::info("Product image sent for {$product->name}", [
                        'image_path' => $image->image_path,
                        'message_id' => $data['message_id'] ?? null,
                    ]);

                    // Small delay between images
                    if ($sentCount < $images->take(3)->count()) {
                        usleep(500000); // 0.5 second delay
                    }
                } else {
                    Log::error("Failed to send product image", [
                        'image_url' => $imageUrl,
                    ]);
                }
            }

            Log::info("Sent {$sentCount} images for product {$product->name}");

        } catch (\Exception $e) {
            Log::error("Exception sending product images: " . $e->getMessage());
        }
    }

    /**
     * Process message attachments.
     */
    protected function processAttachments(array $attachments): array
    {
        $processed = [];

        foreach ($attachments as $attachment) {
            $type = $attachment['type'] ?? 'file';
            $payload = $attachment['payload'] ?? [];

            $processed[] = [
                'type' => $type,
                'url' => $payload['url'] ?? null,
                'sticker_id' => $payload['sticker_id'] ?? null,
                'title' => $payload['title'] ?? null,
            ];
        }

        return $processed;
    }

    /**
     * Fetch participant info from Graph API.
     */
    protected function fetchParticipantInfo(Conversation $conversation, SocialAccount $socialAccount): void
    {
        try {
            $participantId = $conversation->participant_id;
            $accessToken = $socialAccount->provider_token;

            $metaApi = app(MetaApiService::class);
            $data = $metaApi->fetchParticipantInfo($participantId, $accessToken);

            if ($data) {
                $name = $data['name'] ?? null;

                // Fallback to first_name + last_name
                if (!$name && isset($data['first_name'])) {
                    $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                }

                // Fallback to username for Instagram
                if (!$name && isset($data['username'])) {
                    $name = $data['username'];
                }

                if ($name) {
                    $conversation->update([
                        'participant_name' => $name,
                        'participant_avatar' => $data['profile_pic'] ?? null,
                    ]);
                    Log::info("Updated participant info for conversation {$conversation->id}: {$name}");
                }
            } else {
                Log::warning("Failed to fetch participant info for {$participantId}");

                // Set a default name if API fails
                $platformName = match ($conversation->platform) {
                    'instagram' => 'انستقرام',
                    'whatsapp' => 'واتساب',
                    default => 'فيسبوك',
                };
                if (!$conversation->participant_name) {
                    $conversation->update([
                        'participant_name' => "مستخدم {$platformName}",
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching participant info: " . $e->getMessage());
        }
    }

    /**
     * Handle read receipt.
     */
    protected function handleReadReceipt(array $event, SocialAccount $socialAccount): void
    {
        $senderId = $event['sender']['id'];
        $watermark = $event['read']['watermark'] ?? null;

        if (!$watermark) {
            return;
        }

        // Mark messages as read up to the watermark timestamp
        $conversation = Conversation::where('social_account_id', $socialAccount->id)
            ->where('participant_id', $senderId)
            ->first();

        if ($conversation) {
            $readTime = \Carbon\Carbon::createFromTimestampMs($watermark);

            Message::where('conversation_id', $conversation->id)
                ->where('direction', 'outgoing')
                ->where('created_at', '<=', $readTime)
                ->whereNull('read_at')
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                    'status' => 'read',
                ]);

            Log::info("Marked messages as read for conversation {$conversation->id}");
        }
    }

    /**
     * Handle delivery receipt.
     */
    protected function handleDeliveryReceipt(array $event, SocialAccount $socialAccount): void
    {
        $senderId = $event['sender']['id'];
        $watermark = $event['delivery']['watermark'] ?? null;

        if (!$watermark) {
            return;
        }

        $conversation = Conversation::where('social_account_id', $socialAccount->id)
            ->where('participant_id', $senderId)
            ->first();

        if ($conversation) {
            $deliveredTime = \Carbon\Carbon::createFromTimestampMs($watermark);

            Message::where('conversation_id', $conversation->id)
                ->where('direction', 'outgoing')
                ->where('created_at', '<=', $deliveredTime)
                ->where('status', 'sent')
                ->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

            Log::info("Marked messages as delivered for conversation {$conversation->id}");
        }
    }

    /**
     * Process an incoming comment event from Facebook or Instagram.
     *
     * Facebook feed webhook value structure:
     *   - item: "comment"
     *   - comment_id: "..."
     *   - post_id: "..."
     *   - from: { id, name }
     *   - message: "..."
     *   - verb: "add" | "edit" | "remove"
     *
     * Instagram comments webhook value structure:
     *   - id: comment id
     *   - text: "..."
     *   - from: { id, username }
     *   - media: { id, media_product_type }
     */
    protected function processCommentEvent(array $value, ?string $accountId, string $platform): void
    {
        // Feature gate — skip entirely if comments feature is disabled
        if (!config('services.meta.enable_comments', false)) {
            Log::debug('Comment event received but FACEBOOK_ENABLE_COMMENTS is false — skipping');
            return;
        }

        // Only handle new comments (verb = add or no verb for IG)
        $verb = $value['verb'] ?? 'add';
        if ($verb !== 'add') {
            return;
        }

        // Extract fields depending on platform
        if ($platform === 'instagram') {
            $commentId = $value['id'] ?? null;
            $commentText = $value['text'] ?? '';
            $commenterId = $value['from']['id'] ?? null;
            $commenterName = $value['from']['username'] ?? null;
            $postId = $value['media']['id'] ?? null;
            $permalinkUrl = $value['media']['permalink_url'] ?? null;
            $parentId = $value['parent_id'] ?? null; // set when this is a reply to a comment
        } else {
            // Facebook
            $commentId = $value['comment_id'] ?? null;
            $commentText = $value['message'] ?? '';
            $commenterId = $value['from']['id'] ?? null;
            $commenterName = $value['from']['name'] ?? null;
            $postId = $value['post_id'] ?? null;
            // permalink_url lets us extract the real video/photo numeric ID
            $permalinkUrl = $value['post']['permalink_url'] ?? null;
            // parent_id = post_id for top-level comments; a different value means it's a reply to a comment
            $parentId = $value['parent_id'] ?? null;
        }

        if (!$commentId || !$commenterId || !$postId) {
            Log::warning("SocialCommentService: Missing required comment fields", [
                'comment_id' => $commentId,
                'commenter_id' => $commenterId,
                'post_id' => $postId,
                'platform' => $platform,
            ]);
            return;
        }

        // ── Loop-prevention fix 1: skip reply-to-comment events ──────────────
        if ($platform === 'facebook' && $parentId && $parentId !== $postId) {
            Log::info("Skipping Facebook reply-to-comment (loop prevention)", [
                'comment_id' => $commentId,
                'parent_id' => $parentId,
            ]);
            return;
        }
        if ($platform === 'instagram' && $parentId) {
            Log::info("Skipping Instagram reply-to-comment (loop prevention)", [
                'comment_id' => $commentId,
                'parent_id' => $parentId,
            ]);
            return;
        }
        // ── End loop-prevention fix 1 ─────────────────────────────────────────

        // ── Loop-prevention fix 2: skip comments from our own account ─────────
        if ($commenterId === $accountId) {
            Log::info("Skipping own comment on {$platform} (matched account ID)");
            return;
        }
        // Secondary check for Instagram — reply may carry the Facebook Page ID
        if ($platform === 'instagram') {
            $igSocialAccount = SocialAccount::where('provider', 'instagram')
                ->where('provider_id', $accountId)
                ->first();
            if ($igSocialAccount) {
                $fbPageId = data_get($igSocialAccount->meta_data, 'facebook_page_id');
                if ($fbPageId && $commenterId === $fbPageId) {
                    Log::info("Skipping own Instagram comment (matched via facebook_page_id)");
                    return;
                }
            }
        }
        // ── End loop-prevention fix 2 ─────────────────────────────────────────

        // Find social account
        $providerType = $platform === 'instagram' ? 'instagram' : 'facebook_page';
        $socialAccount = SocialAccount::where('provider', $providerType)
            ->where('provider_id', $accountId)
            ->first();

        if (!$socialAccount) {
            Log::warning("SocialCommentService: Social account not found for comment", [
                'account_id' => $accountId,
                'platform' => $platform,
            ]);
            return;
        }

        Log::info("Processing comment on {$platform} post", [
            'comment_id' => $commentId,
            'commenter' => $commenterName,
            'post_id' => $postId,
            'text' => mb_substr($commentText, 0, 100),
        ]);

        // Delegate to SocialCommentService
        $commentService = new SocialCommentService();
        $commentService->handleCommentWebhook(
            $commentId,
            $commentText,
            $commenterId,
            $commenterName,
            $postId,
            $platform,
            $socialAccount,
            $permalinkUrl ?? null
        );
    }

    /**
     * Handle reaction.
     */
    protected function handleReaction(array $event, SocialAccount $socialAccount, string $platform): void
    {
        $reactionData = $event['reaction'] ?? [];
        $messageId = $reactionData['mid'] ?? null;
        $reaction = $reactionData['reaction'] ?? null;
        $action = $reactionData['action'] ?? 'react'; // 'react' or 'unreact'

        if (!$messageId) {
            return;
        }

        $message = Message::where('external_id', $messageId)->first();

        if ($message) {
            $metaData = $message->meta_data ?? [];

            if ($action === 'react') {
                $metaData['reaction'] = $reaction;
            } else {
                unset($metaData['reaction']);
            }

            $message->update(['meta_data' => $metaData]);
            Log::info("Reaction {$action} on message {$message->id}: {$reaction}");
        }
    }
}

