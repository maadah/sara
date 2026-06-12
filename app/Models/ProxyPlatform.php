<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProxyPlatform extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'api_key',
        'api_secret',
        'webhook_url',
        'oauth_callback_url',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    protected $hidden = [
        'api_secret',
    ];

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(ProxySocialAccount::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ProxyApiLog::class);
    }
}
