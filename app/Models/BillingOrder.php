<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingOrder extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    protected $fillable = [
        'order_code',
        'hotel_id',
        'user_id',
        'email',
        'hotel_name',
        'plan',
        'method',
        'amount_vnd',
        'amount_usd_cents',
        'status',
        'locale',
        'transfer_content',
        'qr_image_url',
        'stripe_session_id',
        'stripe_url',
        'expires_at',
        'paid_at',
        'period_starts_at',
        'period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }
}
