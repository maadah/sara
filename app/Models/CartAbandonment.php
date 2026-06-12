<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAbandonment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'lead_id',
        'store_id',
        'cart_snapshot',
        'cart_total',
        'abandoned_at',
    ];

    protected $casts = [
        'cart_snapshot' => 'array',
        'cart_total'    => 'decimal:2',
        'abandoned_at'  => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(User::class, 'store_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
