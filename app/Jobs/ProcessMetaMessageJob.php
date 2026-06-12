<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMetaMessageJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected array $event;
    protected ?string $accountId;
    protected string $platform;

    public function __construct(array $event, ?string $accountId, string $platform)
    {
        $this->event = $event;
        $this->accountId = $accountId;
        $this->platform = $platform;
    }

    public function handle(): void
    {
        // This is a proxy call to the MetaWebhookController's processing logic, 
        // to avoid duplicating the huge logic of handleIncomingMessage.
        // A cleaner architecture would move this to a dedicated Action/Service class,
        // but to maintain full backward compatibility, we instantiate the controller method statically or via DI.
        
        Log::info("ProcessMetaMessageJob started for {$this->platform}");

        $controller = app(\App\Http\Controllers\Webhooks\MetaWebhookController::class);
        $controller->processMessagingEventJob($this->event, $this->accountId, $this->platform);
        
        Log::info("ProcessMetaMessageJob completed");
    }
}
