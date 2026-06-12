<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $senderName;
    public string $platform;
    public string $preview;

    /**
     * Create a new event instance.
     *
     * Accepts either a Message model (legacy callers) or explicit parameters.
     */
    public function __construct(Message $message)
    {
        $conversation = $message->conversation ?? $message->load('conversation')->conversation;
        $socialAccount = $conversation?->socialAccount ?? null;

        $this->userId     = $conversation?->user_id ?? 0;
        $this->senderName = $conversation?->participant_name ?? 'عميل';
        $this->platform   = $conversation?->platform ?? 'facebook';
        $this->preview    = mb_substr($message->content ?? '', 0, 60);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewMessageReceived';
    }
}
