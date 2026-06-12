<?php

namespace App\Http\Controllers\Customer;

use App\Events\ConversationUpdated;
use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MetaApiService;

class MessagesController extends Controller
{
    /**
     * Display the messages inbox.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get filter parameters
        $platform = $request->get('platform'); // 'facebook', 'instagram', or null for all
        $status = $request->get('status', 'active');
        $search = $request->get('search');

        // Get conversations
        $conversationsQuery = $user->conversations()
            ->with(['socialAccount', 'messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->where('status', $status)
            ->orderByDesc('last_message_at');

        if ($platform) {
            $conversationsQuery->where('platform', $platform);
        }

        if ($search) {
            $conversationsQuery->where(function ($query) use ($search) {
                $query->where('participant_name', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($q) use ($search) {
                        $q->where('content', 'like', "%{$search}%");
                    });
            });
        }

        $conversations = $conversationsQuery->paginate(20);

        // Get user's social accounts for filtering
        $socialAccounts = $user->socialAccounts()
            ->whereIn('provider', ['facebook_page', 'instagram', 'whatsapp'])
            ->get();

        // Get unread counts by platform
        $unreadCounts = [
            'all' => $user->conversations()->where('is_read', false)->count(),
            'facebook' => $user->conversations()->where('platform', 'facebook')->where('is_read', false)->count(),
            'instagram' => $user->conversations()->where('platform', 'instagram')->where('is_read', false)->count(),
            'whatsapp' => $user->conversations()->where('platform', 'whatsapp')->where('is_read', false)->count(),
        ];

        return view('customer.inbox.index', compact(
            'conversations',
            'socialAccounts',
            'unreadCounts',
            'platform',
            'status',
            'search'
        ));
    }

    /**
     * Show a specific conversation.
     */
    public function show(Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        // Load relationships
        $conversation->load(['socialAccount', 'messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }, 'lead']);

        // Mark conversation as read
        $conversation->markAsRead();

        // Extract AI context data (cart, customer info)
        $aiContext = $conversation->ai_context ?? [];
        $cart = $aiContext['collected_data']['cart'] ?? [];
        $customerData = $aiContext['collected_data']['customer_data'] ?? [];

        // Get other conversations for sidebar
        $otherConversations = Auth::user()->conversations()
            ->with('socialAccount')
            ->where('status', 'active')
            ->where('id', '!=', $conversation->id)
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get();

        return view('customer.inbox.show', compact(
            'conversation',
            'otherConversations',
            'cart',
            'customerData'
        ));
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string|max:2000',
            'message_type' => 'sometimes|string|in:text,image',
            'attachment' => 'sometimes|file|max:10240', // 10MB max
        ]);

        $user = Auth::user();
        $socialAccount = $conversation->socialAccount;

        // Send message to Facebook/Instagram
        $result = $this->sendToMeta($conversation, $socialAccount, $request->content);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'فشل في إرسال الرسالة',
            ], 400);
        }

        // Create message record
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'external_id' => $result['message_id'] ?? null,
            'direction' => 'outgoing',
            'content' => $request->content,
            'message_type' => 'text',
            'status' => 'sent',
            'is_read' => true,
        ]);

        // Update conversation
        $conversation->update([
            'last_message' => $request->content,
            'last_message_at' => now(),
        ]);

        // Broadcast the new message
        event(new NewMessageReceived($message));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'direction' => $message->direction,
                'created_at' => $message->created_at->toIso8601String(),
                'formatted_time' => $message->formatted_time,
            ],
        ]);
    }

    /**
     * Send message to Meta (Facebook/Instagram).
     */
    protected function sendToMeta(Conversation $conversation, SocialAccount $socialAccount, string $message): array
    {
        try {
            $metaApi     = app(MetaApiService::class);
            $recipientId = $conversation->participant_id;
            $platform    = ($conversation->platform === 'facebook' || $socialAccount->provider === 'facebook_page')
                ? 'facebook' : 'instagram';

            $pageId = $socialAccount->provider_id;

            if ($platform === 'instagram') {
                $pageId = data_get($socialAccount->meta_data, 'facebook_page_id', $socialAccount->provider_id);
            }

            $data = $metaApi->sendMessage(
                $pageId,
                $socialAccount->provider_token,
                $recipientId,
                $message,
                $platform
            );

            if ($data) {
                return [
                    'success'    => true,
                    'message_id' => $data['message_id'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error'   => 'Failed to send message',
            ];
        } catch (\Exception $e) {
            Log::error('Exception sending message to Meta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an image to Meta (Facebook/Instagram).
     */
    protected function sendImageToMeta(Conversation $conversation, SocialAccount $socialAccount, string $imageUrl): array
    {
        try {
            $metaApi     = app(MetaApiService::class);
            $recipientId = $conversation->participant_id;
            $platform    = ($conversation->platform === 'facebook' || $socialAccount->provider === 'facebook_page')
                ? 'facebook' : 'instagram';

            $pageId = $socialAccount->provider_id;

            if ($platform === 'instagram') {
                $pageId = data_get($socialAccount->meta_data, 'facebook_page_id', $socialAccount->provider_id);
            }

            $data = $metaApi->sendImage(
                $pageId,
                $socialAccount->provider_token,
                $recipientId,
                $imageUrl,
                $platform
            );

            if ($data) {
                return [
                    'success'    => true,
                    'message_id' => $data['message_id'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error'   => 'Failed to send image',
            ];
        } catch (\Exception $e) {
            Log::error('Exception sending image to Meta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an image message in a conversation.
     */
    public function sendImage(Request $request, Conversation $conversation)
    {
        // Authorize access
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        $user = Auth::user();
        $socialAccount = $conversation->socialAccount;

        // Store the image
        $path = $request->file('image')->store('chat-images', 'public');
        $imageUrl = url('storage/' . $path);

        // Send image to Facebook/Instagram
        $result = $this->sendImageToMeta($conversation, $socialAccount, $imageUrl);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'فشل في إرسال الصورة',
            ], 400);
        }

        // Create message record
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'external_id' => $result['message_id'] ?? null,
            'direction' => 'outgoing',
            'content' => null,
            'message_type' => 'image',
            'attachments' => [
                ['type' => 'image', 'url' => $imageUrl],
            ],
            'status' => 'sent',
            'is_read' => true,
        ]);

        // Update conversation
        $conversation->update([
            'last_message' => '📷 صورة',
            'last_message_at' => now(),
        ]);

        // Broadcast the new message
        event(new NewMessageReceived($message));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'content' => null,
                'message_type' => 'image',
                'attachments' => $message->attachments,
                'direction' => $message->direction,
                'created_at' => $message->created_at->toIso8601String(),
                'formatted_time' => $message->formatted_time,
            ],
        ]);
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(Conversation $conversation)
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $conversation->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Archive a conversation.
     */
    public function archive(Conversation $conversation)
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $conversation->update(['status' => 'archived']);

        return response()->json(['success' => true]);
    }

    /**
     * Restore an archived conversation.
     */
    public function restore(Conversation $conversation)
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $conversation->update(['status' => 'active']);

        return response()->json(['success' => true]);
    }

    /**
     * Get messages for a conversation (AJAX).
     */
    public function getMessages(Conversation $conversation, Request $request)
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'message_type' => $message->message_type,
                    'direction' => $message->direction,
                    'attachments' => $message->attachments,
                    'created_at' => $message->created_at->toIso8601String(),
                    'formatted_time' => $message->formatted_time,
                    'status' => $message->status,
                    'is_read' => $message->is_read,
                ];
            });

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Sync messages from Meta platforms.
     * This fetches existing conversations and messages from Facebook/Instagram.
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        $synced = 0;

        // Get all Facebook Pages and Instagram accounts
        $socialAccounts = $user->socialAccounts()
            ->whereIn('provider', ['facebook_page', 'instagram'])
            ->get();

        foreach ($socialAccounts as $account) {
            try {
                $synced += $this->syncAccountConversations($account);
            } catch (\Exception $e) {
                Log::error("Error syncing account {$account->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "تم مزامنة {$synced} محادثة",
            'synced' => $synced,
        ]);
    }

    /**
     * Sync conversations for a specific social account.
     */
    protected function syncAccountConversations(SocialAccount $account): int
    {
        // Reload from DB to ensure the record still exists (guards against race conditions
        // when accounts are re-linked between the initial query and the sync loop)
        $account = $account->fresh();
        if (!$account) {
            Log::warning('Social account no longer exists, skipping sync');
            return 0;
        }

        $synced = 0;
        $accessToken = $account->provider_token;
        $platform = $account->provider === 'instagram' ? 'instagram' : 'facebook';

        $metaApi = app(\App\Services\MetaApiService::class);
        $data = $metaApi->fetchConversations($account->provider_id, $accessToken, $platform);

        if (!$data) {
            Log::warning("Failed to fetch conversations for account {$account->id}");
            return 0;
        }

        $conversations = $data['data'] ?? [];

        foreach ($conversations as $convData) {
            try {
                $this->importConversation($account, $convData, $platform);
                $synced++;
            } catch (\Exception $e) {
                Log::error("Error importing conversation: " . $e->getMessage());
            }
        }

        return $synced;
    }

    /**
     * Import a conversation from Meta API data.
     */
    protected function importConversation(SocialAccount $account, array $convData, string $platform): void
    {
        $participants = $convData['participants']['data'] ?? [];
        $messages = $convData['messages']['data'] ?? [];

        // Find the external participant (not the page)
        $externalParticipant = null;
        foreach ($participants as $participant) {
            if ($participant['id'] !== $account->provider_id) {
                $externalParticipant = $participant;
                break;
            }
        }

        if (!$externalParticipant) {
            return;
        }

        // Get participant name - try multiple sources
        $participantName = $externalParticipant['name'] ?? null;
        $participantEmail = $externalParticipant['email'] ?? null;

        // If no name, try to fetch it from Graph API
        if (!$participantName) {
            $participantName = $this->fetchParticipantName($externalParticipant['id'], $account, $platform);
        }

        // Create or update conversation
        $conversation = Conversation::updateOrCreate(
            [
                'social_account_id' => $account->id,
                'participant_id' => $externalParticipant['id'],
                'platform' => $platform,
            ],
            [
                'user_id' => $account->user_id,
                'participant_name' => $participantName,
                'thread_id' => $convData['id'] ?? null,
                'status' => 'active',
            ]
        );

        // Import messages
        foreach (array_reverse($messages) as $msgData) {
            $this->importMessage($conversation, $msgData, $account);
        }

        // Update last message info
        if (!empty($messages)) {
            $lastMsg = $messages[0];
            $conversation->update([
                'last_message' => $lastMsg['message'] ?? null,
                'last_message_at' => isset($lastMsg['created_time'])
                    ? \Carbon\Carbon::parse($lastMsg['created_time'])
                    : now(),
            ]);
        }
    }

    /**
     * Fetch participant name from Graph API.
     */
    protected function fetchParticipantName(string $participantId, SocialAccount $account, string $platform): ?string
    {
        try {
            $metaApi = app(\App\Services\MetaApiService::class);
            $data = $metaApi->fetchParticipantInfo($participantId, $account->provider_token);

            if ($data) {
                if (!empty($data['name'])) {
                    return $data['name'];
                }
                if (!empty($data['first_name'])) {
                    return trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                }
                if (!empty($data['username'])) {
                    return '@' . $data['username'];
                }
            }

            Log::warning("Could not fetch participant name for ID: {$participantId}");
        } catch (\Exception $e) {
            Log::error("Error fetching participant name: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Refresh participant info from Meta API.
     */
    public function refreshParticipant(Conversation $conversation)
    {
        if ($conversation->user_id !== Auth::id()) {
            abort(403);
        }

        $socialAccount = $conversation->socialAccount;

        if (!$socialAccount) {
            return response()->json([
                'success' => false,
                'error' => 'لا يوجد حساب متصل',
            ], 400);
        }

        try {
            $metaApi = app(\App\Services\MetaApiService::class);
            $data = $metaApi->fetchParticipantInfo($conversation->participant_id, $socialAccount->provider_token);

            if ($data) {
                $name = null;

                // Try name first
                if (!empty($data['name'])) {
                    $name = $data['name'];
                }
                // Fallback to first_name + last_name
                elseif (!empty($data['first_name'])) {
                    $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                }
                // Fallback to username for Instagram
                elseif (!empty($data['username'])) {
                    $name = '@' . $data['username'];
                }

                // Update conversation
                if ($name) {
                    $conversation->update([
                        'participant_name' => $name,
                        'participant_avatar' => $data['profile_pic'] ?? null,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'تم تحديث بيانات المحادث',
                        'participant_name' => $name,
                        'participant_avatar' => $data['profile_pic'] ?? null,
                    ]);
                }
            }

            Log::warning("Could not fetch participant info for ID: {$participantId}", [
                'response' => $response->json(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'لم يتم العثور على بيانات المستخدم',
            ], 400);
        } catch (\Exception $e) {
            Log::error("Error refreshing participant info: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import a single message.
     */
    protected function importMessage(Conversation $conversation, array $msgData, SocialAccount $account): void
    {
        $externalId = $msgData['id'] ?? null;

        // Skip if already exists
        if ($externalId && Message::where('external_id', $externalId)->exists()) {
            return;
        }

        $fromId = $msgData['from']['id'] ?? null;
        $direction = ($fromId === $account->provider_id) ? 'outgoing' : 'incoming';

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $direction === 'outgoing' ? $account->user_id : null,
            'external_id' => $externalId,
            'direction' => $direction,
            'content' => $msgData['message'] ?? null,
            'message_type' => 'text',
            'status' => 'delivered',
            'is_read' => true,
            'platform_created_at' => isset($msgData['created_time'])
                ? \Carbon\Carbon::parse($msgData['created_time'])
                : now(),
        ]);
    }
}

