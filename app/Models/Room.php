<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'code',
    'name',
    'kind',
    'current_welcome_id',
    'content_revision',
    'default_media_id',
    'is_active',
])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'content_revision' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function currentWelcome(): BelongsTo
    {
        return $this->belongsTo(WelcomeContent::class, 'current_welcome_id');
    }

    public function welcomes(): HasMany
    {
        return $this->hasMany(WelcomeContent::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function defaultMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'default_media_id');
    }

    public function bumpRevision(): void
    {
        $this->increment('content_revision');
        $this->refresh();
    }
}
