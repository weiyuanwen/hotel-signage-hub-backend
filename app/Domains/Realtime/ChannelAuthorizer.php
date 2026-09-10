<?php

namespace App\Domains\Realtime;

use App\Models\Device;
use App\Models\Room;
use App\Models\User;

class ChannelAuthorizer
{
    public function canJoinRoom(mixed $actor, int $hotelId, int $roomId): bool
    {
        if ($actor instanceof Device) {
            return $actor->isPaired()
                && (int) $actor->hotel_id === $hotelId
                && (int) $actor->room_id === $roomId;
        }

        if ($actor instanceof User) {
            return $actor->canAccessHotel($hotelId)
                && Room::query()->whereKey($roomId)->where('hotel_id', $hotelId)->exists();
        }

        return false;
    }

    public function canJoinDevice(mixed $actor, int $deviceId): bool
    {
        if ($actor instanceof Device) {
            return (int) $actor->id === $deviceId;
        }

        if ($actor instanceof User) {
            $device = Device::query()->find($deviceId);

            return $device?->hotel_id && $actor->canAccessHotel((int) $device->hotel_id);
        }

        return false;
    }
}
