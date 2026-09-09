<?php

namespace App\Domains\Device;

use App\Models\Device;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Room;

class ScreenDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forDevice(Device $device): array
    {
        $device->loadMissing(['hotel.logo', 'hotel.defaultMedia', 'room.currentWelcome', 'room.defaultMedia']);

        $hotel = $device->hotel;
        $room = $device->room;

        if (! $hotel || ! $room) {
            abort(409, 'Device is not paired to a room.');
        }

        return $this->forRoom($hotel, $room);
    }

    /**
     * @return array<string, mixed>
     */
    public function forRoom(Hotel $hotel, Room $room): array
    {
        $hotel->loadMissing(['logo', 'defaultMedia']);
        $room->loadMissing(['currentWelcome', 'defaultMedia']);

        $stay = $room->currentWelcome;
        $media = $room->defaultMedia ?? $hotel->defaultMedia;

        return [
            'hotel' => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'default_locale' => $hotel->default_locale,
                'logo_url' => $hotel->logo?->url(),
            ],
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'kind' => $room->kind,
                'content_revision' => $room->content_revision,
            ],
            'guest' => $stay ? [
                'display_name' => $stay->guest_display_name,
                'message' => $stay->message,
                'locale' => $stay->locale,
            ] : null,
            'template' => $stay ? [
                'key' => $stay->template_key,
            ] : null,
            'media' => [
                'background_url' => $media instanceof MediaAsset ? $media->url() : null,
            ],
        ];
    }
}
