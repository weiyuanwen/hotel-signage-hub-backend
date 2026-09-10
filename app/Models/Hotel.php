<?php

namespace App\Models;

use App\Domains\Billing\HotelPlan;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone', 'default_locale', 'weather_region', 'wifi_ssid', 'wifi_password', 'is_active', 'plan', 'device_limit', 'logo_media_id', 'default_media_id', 'default_welcome_template_key'])]
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'device_limit' => 'integer',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function pairingMode(): string
    {
        return HotelPlan::pairingMode($this->plan ?? HotelPlan::PREMIUM);
    }

    public function allowsPairingLinks(): bool
    {
        return HotelPlan::allowsPairingLinks($this->plan ?? HotelPlan::PREMIUM);
    }

    public function pairedDeviceCount(): int
    {
        if (array_key_exists('paired_device_count', $this->attributes)) {
            return (int) $this->attributes['paired_device_count'];
        }

        return $this->devices()->where('status', 'paired')->count();
    }

    public function hasDeviceCapacity(): bool
    {
        if ($this->device_limit === null) {
            return true;
        }

        return $this->pairedDeviceCount() < $this->device_limit;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlanPayload(): array
    {
        return [
            'plan' => $this->plan ?? HotelPlan::PREMIUM,
            'plan_label' => HotelPlan::label($this->plan ?? HotelPlan::PREMIUM),
            'device_limit' => $this->device_limit,
            'pairing_mode' => $this->pairingMode(),
            'paired_device_count' => $this->pairedDeviceCount(),
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function defaultMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'default_media_id');
    }

    public function welcomeTemplates(): HasMany
    {
        return $this->hasMany(HotelWelcomeTemplate::class);
    }
}
