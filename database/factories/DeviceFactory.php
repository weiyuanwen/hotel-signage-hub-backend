<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => null,
            'room_id' => null,
            'name' => 'TV-'.fake()->unique()->numerify('###'),
            'status' => 'pending',
        ];
    }

    public function paired(?Hotel $hotel = null, ?Room $room = null): static
    {
        return $this->state(function () use ($hotel, $room) {
            $hotel ??= Hotel::factory()->create();
            $room ??= Room::factory()->create(['hotel_id' => $hotel->id]);

            return [
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
                'status' => 'paired',
                'paired_at' => now(),
            ];
        });
    }
}
