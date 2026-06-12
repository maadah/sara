<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatMessage — a single message (user, assistant, or tool) within a chat session.
 *
 * Every message stores the detected intent, extracted entities, any tool calls
 * that were made, and the token count for cost tracking.
 *
 * @property int         $id
 * @property int         $session_id
 * @property string      $role
 * @property string      $content
 * @property string|null $intent
 * @property array|null  $entities
 * @property array|null  $tool_calls
 * @property int|null    $tokens_used
 * @property \Carbon\Carbon $created_at
 */
class ChatMessage extends Model
{
    public $timestamps = false;

    protected $table = 'chat_messages';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'intent',
        'entities',
        'tool_calls',
        'tokens_used',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entities'   => 'array',
            'tool_calls' => 'array',
            'tokens_used' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
