<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTransaction extends Model
{
    protected $fillable = [
        'billing_order_id',
        'provider',
        'provider_txn_id',
        'amount',
        'currency',
        'description',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }
}
