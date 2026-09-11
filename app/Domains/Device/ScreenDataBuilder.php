<?php

namespace App\Domains\Device;

use App\Domains\Content\TemplateLayout;
use App\Domains\Content\WeatherRegion;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use App\Models\MediaAsset;
use App\Models\Room;

class ScreenDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forDevice(Device $device): array
    {
        $device = $device->fresh(['hotel', 'room', 'defaultMedia']) ?? $device;
        $hotel = $device->hotel;
        $room = $device->room;

        if (! $hotel || ! $room) {
            abort(409, 'Device is not paired to a room.');
        }

        return $this->build($hotel, $room, $device->defaultMedia);
    }

    /**
     * @return array<string, mixed>
     */
    public function forRoom(Hotel $hotel, Room $room): array
    {
        return $this->build($hotel, $room, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Hotel $hotel, Room $room, ?MediaAsset $deviceMedia): array
    {
        $hotel = $hotel->fresh(['logo', 'defaultMedia']) ?? $hotel;
        $room = $room->fresh(['currentWelcome', 'defaultMedia']) ?? $room;

        $stay = $room->currentWelcome;
        $baseMedia = $deviceMedia ?? $room->defaultMedia ?? $hotel->defaultMedia;
        $useVideo = $stay && $this->isVideo($baseMedia);

        $templateRow = null;
        $layout = null;
        if ($stay && ! $useVideo) {
            $templateRow = HotelWelcomeTemplate::query()
                ->with('backgroundMedia')
                ->where('hotel_id', $hotel->id)
                ->where('template_key', $stay->template_key)
                ->first();
            $layout = TemplateLayout::normalize($templateRow?->layout, $stay->template_key);
            $templateUrl = TemplateLayout::resolveBackgroundUrl($layout, $templateRow?->backgroundMedia?->url());
            $media = $deviceMedia ?: ($templateUrl
                ? new MediaAsset([
                    'type' => 'image',
                    'disk' => 'external',
                    'path' => $templateUrl,
                ])
                : $baseMedia);
        } else {
            $media = $baseMedia;
        }

        return [
            'hotel' => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'timezone' => $hotel->timezone,
                'default_locale' => $hotel->default_locale,
                'logo_url' => $hotel->logo?->url(),
                'wifi' => $hotel->wifi_ssid ? [
                    'ssid' => $hotel->wifi_ssid,
                    'password' => $hotel->wifi_password,
                ] : null,
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
                'mode' => $useVideo ? 'video' : 'look',
                'layout' => $useVideo ? null : $layout,
            ] : null,
            'media' => [
                'background_url' => $media instanceof MediaAsset ? $media->url() : null,
                'kind' => $media instanceof MediaAsset
                    ? ($this->isVideo($media) ? 'video' : 'image')
                    : null,
            ],
            'weather' => WeatherRegion::get($hotel->weather_region),
        ];
    }

    private function isVideo(?MediaAsset $media): bool
    {
        return $media instanceof MediaAsset && $media->type === 'video';
    }
}
