<?php

namespace App\Models;

use App\Domains\Content\WelcomeTemplateKey;
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
        'layout',
        'background_media_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'layout' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function backgroundMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'background_media_id');
    }

    public function label(): string
    {
        if (is_string($this->display_name) && $this->display_name !== '') {
            return $this->display_name;
        }

        $key = WelcomeTemplateKey::tryFrom($this->template_key);

        return $key?->builtInLabel() ?? $this->template_key;
    }
}
