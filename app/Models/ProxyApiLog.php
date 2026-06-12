<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proxy_platform_id',
        'action',
        'provider_id',
        'status',
        'details',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(ProxyPlatform::class, 'proxy_platform_id');
    }
}
