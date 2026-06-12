<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CustomerProfile — extended CRM record per store + lead.
 *
 * Tracks lead scoring, VIP tags, order history counters,
 * and browsing preferences for personalised marketing.
 *
 * @property int         $id
 * @property int         $store_id
 * @property int         $lead_id
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $notes
 * @property array|null  $tags
 * @property int         $lead_score
 * @property int         $total_orders
 * @property \Carbon\Carbon|null $last_order_at
 * @property array|null  $preferences
 * — Demographics (collected by AI) —
 * @property int|null    $age
 * @property string|null $gender           male|female|other
 * @property int|null    $budget_min       IQD
 * @property int|null    $budget_max       IQD
 * @property string|null $occupation
 * @property string|null $marital_status   single|married|divorced|other
 * @property array|null  $interests
 * @property string|null $social_platform  facebook|instagram|whatsapp|web
 */
class CustomerProfile extends Model
{
    protected $table = 'customer_profiles';

    protected $fillable = [
        'store_id',
        'lead_id',
        'name',
        'phone',
        'address',
        'city',
        'notes',
        'tags',
        'lead_score',
        'total_orders',
        'last_order_at',
        'preferences',
        // Demographics
        'age',
        'gender',
        'budget_min',
        'budget_max',
        'occupation',
        'marital_status',
        'social_platform',
    ];

    protected function casts(): array
    {
        return [
            'tags'           => 'array',
            'lead_score'     => 'integer',
            'total_orders'   => 'integer',
            'last_order_at'  => 'datetime',
            'preferences'    => 'array',

            'age'            => 'integer',
            'budget_min'     => 'integer',
            'budget_max'     => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function store(): BelongsTo
    {
        return $this->belongsTo(User::class, 'store_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /* ------------------------------------------------------------------ */
    /* Lead Scoring Helpers                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Adjust the lead score by a delta and persist.
     */
    public function adjustScore(int $delta): void
    {
        $this->lead_score = max(0, $this->lead_score + $delta);
        $this->save();
    }

    /**
     * Determine the score category label.
     *
     * @return string cold|warm|hot|vip
     */
    public function scoreCategory(): string
    {
        $categories = config('chat.score_categories', [
            'cold' => [0, 9],
            'warm' => [10, 24],
            'hot'  => [25, 49],
            'vip'  => [50, PHP_INT_MAX],
        ]);

        foreach ($categories as $label => [$min, $max]) {
            if ($this->lead_score >= $min && $this->lead_score <= $max) {
                return $label;
            }
        }

        return 'cold';
    }

    /**
     * Is this a returning customer with at least one order?
     */
    public function isReturning(): bool
    {
        return $this->total_orders > 0;
    }

    /**
     * Is this customer a VIP?
     */
    public function isVip(): bool
    {
        return $this->scoreCategory() === 'vip';
    }
}
