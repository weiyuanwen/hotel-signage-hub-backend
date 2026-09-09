<?php

namespace App\Models;

use Database\Factories\HotelWelcomeTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelWelcomeTemplate extends Model
{
    /** @use HasFactory<HotelWelcomeTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'template_key',
        'is_enabled',
        'display_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function label(): string
    {
        if (is_string($this->display_name) && $this->display_name !== '') {
            return $this->display_name;
        }

        $key = \App\Domains\Content\WelcomeTemplateKey::tryFrom($this->template_key);

        return $key?->builtInLabel() ?? $this->template_key;
    }
}
