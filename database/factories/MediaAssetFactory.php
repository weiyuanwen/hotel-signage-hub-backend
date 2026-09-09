<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'type' => 'image',
            'disk' => 'public',
            'path' => 'branding/'.fake()->uuid().'.jpg',
            'mime' => 'image/jpeg',
            'bytes' => 1024,
        ];
    }
}
