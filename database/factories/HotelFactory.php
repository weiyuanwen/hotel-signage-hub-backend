<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'default_locale' => 'vi',
            'weather_region' => 'ho-chi-minh',
            'is_active' => true,
            'plan' => 'premium',
            'device_limit' => null,
        ];
    }
}
