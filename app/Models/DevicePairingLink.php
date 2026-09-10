<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['token', 'hotel_id', 'room_id', 'device_id', 'created_by', 'expires_at', 'consumed_at'])]
class DevicePairingLink extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function playerUrl(): string
    {
        return rtrim((string) config('app.player_url'), '/').'/?pair='.$this->token;
    }

    public function isOpen(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
