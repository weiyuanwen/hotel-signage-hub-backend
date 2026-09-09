<?php

namespace App\Models;

use Database\Factories\WelcomeContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'room_id',
    'guest_display_name',
    'message',
    'locale',
    'source',
    'external_ref',
    'is_current',
    'checked_in_at',
    'checked_out_at',
    'created_by',
    'updated_by',
    'template_key',
])]
class WelcomeContent extends Model
{
    /** @use HasFactory<WelcomeContentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
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
}
