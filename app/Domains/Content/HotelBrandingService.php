<?php

namespace App\Domains\Content;

use App\Domains\Device\ScreenDataBuilder;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Domains\Realtime\Events\RoomContentUpdated;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use App\Models\MediaAsset;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;

class HotelBrandingService
{
    public function __construct(private ScreenDataBuilder $screens) {}

    public function clear(Hotel $hotel, string $purpose): void
    {
        $column = $purpose === 'logo' ? 'logo_media_id' : 'default_media_id';
        $hotel->load(['logo', 'defaultMedia']);
        $previous = $purpose === 'logo' ? $hotel->logo : $hotel->defaultMedia;

        if (! $previous) {
            return;
        }

        $hotel->forceFill([$column => null])->save();
        $this->deleteIfOrphaned($previous);
        $this->touchScreens($hotel->fresh() ?? $hotel);
    }

    public function assignRoomBackground(Room $room, MediaAsset $asset): void
    {
        $room->load('defaultMedia');
        $previous = $room->defaultMedia;
        $room->forceFill(['default_media_id' => $asset->id])->save();

        if ($previous && $previous->id !== $asset->id) {
            $this->deleteIfOrphaned($previous);
        }

        $this->touchRoom($room);
    }

    public function clearRoomBackground(Room $room): void
    {
        $room->load('defaultMedia');
        $previous = $room->defaultMedia;

        if (! $previous) {
            return;
        }

        $room->forceFill(['default_media_id' => null])->save();
        $this->deleteIfOrphaned($previous);
        $this->touchRoom($room);
    }

    public function assignDeviceBackground(Device $device, MediaAsset $asset): void
    {
        $device->load('defaultMedia');
        $previous = $device->defaultMedia;
        $device->forceFill(['default_media_id' => $asset->id])->save();

        if ($previous && $previous->id !== $asset->id) {
            $this->deleteIfOrphaned($previous);
        }

        $this->touchDevice($device);
    }

    public function clearDeviceBackground(Device $device): void
    {
        $device->load('defaultMedia');
        $previous = $device->defaultMedia;

        if (! $previous) {
            return;
        }

        $device->forceFill(['default_media_id' => null])->save();
        $this->deleteIfOrphaned($previous);
        $this->touchDevice($device);
    }

    public function touchDevice(Device $device): void
    {
        $fresh = $device->fresh(['hotel', 'room', 'defaultMedia']);
        if (! $fresh) {
            return;
        }

        event(new DeviceCommandIssued($fresh, 'reload'));
    }

    public function touchRoom(Room $room): void
    {
        $room->bumpRevision();
        $fresh = $room->fresh(['hotel.logo', 'hotel.defaultMedia', 'currentWelcome', 'defaultMedia']);
        if (! $fresh || ! $fresh->hotel) {
            return;
        }

        event(new RoomContentUpdated($fresh, $this->screens->forRoom($fresh->hotel, $fresh)));
    }

    public function deleteIfOrphaned(MediaAsset $asset): void
    {
        $id = $asset->id;
        $stillUsed = Hotel::query()
            ->where(function ($query) use ($id) {
                $query->where('logo_media_id', $id)->orWhere('default_media_id', $id);
            })
            ->exists()
            || Room::query()->where('default_media_id', $id)->exists()
            || Device::query()->where('default_media_id', $id)->exists()
            || HotelWelcomeTemplate::query()->where('background_media_id', $id)->exists();

        if ($stillUsed) {
            return;
        }

        if ($asset->disk === 'public') {
            Storage::disk('public')->delete($asset->path);
        }

        $asset->delete();
    }

    public function touchTemplate(Hotel $hotel, string $templateKey): void
    {
        $rooms = Room::query()
            ->where('hotel_id', $hotel->id)
            ->whereHas('currentWelcome', fn ($query) => $query->where('template_key', $templateKey))
            ->get();

        foreach ($rooms as $room) {
            $this->touchRoom($room);
        }
    }

    public function touchScreens(Hotel $hotel): void
    {
        $hotel->load(['logo', 'defaultMedia']);

        $rooms = Room::query()->where('hotel_id', $hotel->id)->get();

        foreach ($rooms as $room) {
            $room->bumpRevision();
            $fresh = $room->fresh(['hotel.logo', 'hotel.defaultMedia', 'currentWelcome', 'defaultMedia']);
            if (! $fresh) {
                continue;
            }

            event(new RoomContentUpdated($fresh, $this->screens->forRoom($fresh->hotel, $fresh)));
        }
    }
}
