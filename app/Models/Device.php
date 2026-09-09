<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['hotel_id', 'room_id', 'name', 'status', 'paired_at', 'last_seen_at'])]
#[Hidden(['remember_token'])]
class Device extends Authenticatable
{
    /** @use HasFactory<DeviceFactory> */
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(DevicePairingCode::class);
    }

    public function isPaired(): bool
    {
        return $this->status === 'paired';
    }
}
