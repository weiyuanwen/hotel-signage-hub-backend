<?php

namespace App\Domains\Content;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;

class WelcomeTemplateCatalog
{
    public function syncHotel(Hotel $hotel): void
    {
        foreach (WelcomeTemplateKey::cases() as $key) {
            HotelWelcomeTemplate::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'template_key' => $key->value,
                ],
                [
                    'is_enabled' => true,
                    'display_name' => null,
                    'sort_order' => $key->sortOrder(),
                ],
            );
        }
    }

    public function isEnabled(Hotel $hotel, string $key): bool
    {
        $this->syncHotel($hotel);

        return HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $key)
            ->where('is_enabled', true)
            ->exists();
    }
}
