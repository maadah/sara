<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommentReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $message;
    public string $commenterName;
    public string $platform;
    public string $productName;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, string $commenterName = '', string $platform = 'facebook', string $productName = '')
    {
        $this->userId = $userId;
        $this->commenterName = $commenterName;
        $this->platform = $platform;
        $this->productName = $productName;
        $this->message = $productName
            ? "تعليق جديد على {$productName} من {$commenterName} 💬"
            : "تعليق جديد من {$commenterName} 💬";
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
        return 'NewCommentReceived';
    }
}
