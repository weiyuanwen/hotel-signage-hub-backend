<?php

namespace App\Domains\Device;

use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\DevicePairingLink;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PairingService
{
    public function __construct(private PairingCodeGenerator $codes) {}

    /**
     * @return array{device: Device, code: DevicePairingCode}
     */
    public function requestCode(?string $name = null): array
    {
        return DB::transaction(function () use ($name) {
            $device = Device::query()->create([
                'name' => $name,
                'status' => 'pending',
            ]);

            $code = $this->uniqueCode();

            $pairing = DevicePairingCode::query()->create([
                'code' => $code,
                'device_id' => $device->id,
                'expires_at' => now()->addSeconds(90),
            ]);

            return ['device' => $device, 'code' => $pairing];
        });
    }

    /**
     * @return array{status: string, token?: string, device_id?: int, hotel_id?: int, room_id?: int}
     */
    public function poll(string $code): array
    {
        return DB::transaction(function () use ($code) {
            $pairing = DevicePairingCode::query()->where('code', $code)->lockForUpdate()->first();

            if (! $pairing || ($pairing->isExpired() && $pairing->claimed_at === null)) {
                throw ValidationException::withMessages([
                    'code' => 'Pairing code is invalid or expired.',
                ]);
            }

            if ($pairing->isPending()) {
                return ['status' => 'pending'];
            }

            $device = $pairing->device()->lockForUpdate()->firstOrFail();

            if (! $device->isPaired()) {
                return ['status' => 'pending'];
            }

            $payload = [
                'status' => 'paired',
                'device_id' => $device->id,
                'hotel_id' => (int) $device->hotel_id,
                'room_id' => (int) $device->room_id,
            ];

            if ($device->tokens()->exists()) {
                return $payload;
            }

            if ($pairing->claimed_at === null || $pairing->claimed_at->lt(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'code' => 'Pairing code is invalid or expired.',
                ]);
            }

            $payload['token'] = $device->createToken('tv', ['device'])->plainTextToken;

            return $payload;
        });
    }

    public function claim(string $code, Room $room, User $actor): Device
    {
        if (! $room->is_active) {
            throw ValidationException::withMessages([
                'room_id' => 'Room is inactive.',
            ]);
        }

        return DB::transaction(function () use ($code, $room, $actor) {
            $pairing = DevicePairingCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $pairing || ! $pairing->isPending()) {
                throw ValidationException::withMessages([
                    'code' => 'Pairing code is invalid or expired.',
                ]);
            }

            $device = $pairing->device()->lockForUpdate()->firstOrFail();
            $hotel = Hotel::query()->lockForUpdate()->findOrFail($room->hotel_id);

            if (! $hotel->hasDeviceCapacity()) {
                throw ValidationException::withMessages([
                    'code' => 'Gói hiện tại đã đủ số TV. Nâng cấp để ghép thêm.',
                ]);
            }

            $device->forceFill([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'status' => 'paired',
                'paired_at' => now(),
            ])->save();

            $pairing->forceFill([
                'claimed_at' => now(),
                'claimed_by' => $actor->id,
            ])->save();

            return $device->fresh();
        });
    }

    public function createLink(Hotel $hotel, Room $room, User $actor): DevicePairingLink
    {
        if ($room->hotel_id !== $hotel->id) {
            throw ValidationException::withMessages([
                'room_id' => 'Room does not belong to this hotel.',
            ]);
        }

        if (! $room->is_active) {
            throw ValidationException::withMessages([
                'room_id' => 'Room is inactive.',
            ]);
        }

        if (! $hotel->allowsPairingLinks()) {
            throw ValidationException::withMessages([
                'plan' => 'Gói này ghép TV bằng mã PIN trên màn hình.',
            ]);
        }

        if (! $hotel->hasDeviceCapacity()) {
            throw ValidationException::withMessages([
                'room_id' => 'Gói hiện tại đã đủ số TV. Nâng cấp để ghép thêm.',
            ]);
        }

        return DevicePairingLink::query()->create([
            'token' => Str::lower(Str::random(48)),
            'hotel_id' => $hotel->id,
            'room_id' => $room->id,
            'created_by' => $actor->id,
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * @return array{status: string, token: string, device_id: int, hotel_id: int, room_id: int}
     */
    public function consumeLink(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $link = DevicePairingLink::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $link || ! $link->isOpen()) {
                throw ValidationException::withMessages([
                    'token' => 'Link ghép TV không còn hiệu lực.',
                ]);
            }

            $hotel = Hotel::query()->lockForUpdate()->findOrFail($link->hotel_id);

            if (! $hotel->hasDeviceCapacity()) {
                throw ValidationException::withMessages([
                    'token' => 'Gói hiện tại đã đủ số TV. Nâng cấp để ghép thêm.',
                ]);
            }

            $room = Room::query()->findOrFail($link->room_id);

            if (! $room->is_active) {
                throw ValidationException::withMessages([
                    'token' => 'Phòng này đã ngưng.',
                ]);
            }

            $device = Device::query()->create([
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
                'name' => 'TV Player',
                'status' => 'paired',
                'paired_at' => now(),
            ]);

            $link->forceFill([
                'device_id' => $device->id,
                'consumed_at' => now(),
            ])->save();

            return [
                'status' => 'paired',
                'token' => $device->createToken('tv', ['device'])->plainTextToken,
                'device_id' => $device->id,
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
            ];
        });
    }

    public function unpair(Device $device): void
    {
        $device->tokens()->delete();
        $device->forceFill([
            'status' => 'revoked',
        ])->save();

        event(new DeviceCommandIssued($device, 'unpair'));
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = $this->codes->make();

            if (! DevicePairingCode::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Unable to allocate a pairing code.',
        ]);
    }
}
