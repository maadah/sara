<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'message',
        'target_audience',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
