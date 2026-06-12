<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxySocialAccount extends Model
{
    protected $fillable = [
        'proxy_platform_id',
        'external_user_id',
        'provider',
        'provider_id',
        'provider_token',
        'name',
        'avatar',
        'meta_data',
        'granted_permissions',
    ];

    protected $casts = [
        'meta_data'           => 'array',
        'granted_permissions' => 'array',
    ];

    protected $hidden = [
        'provider_token',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(ProxyPlatform::class, 'proxy_platform_id');
    }
}
