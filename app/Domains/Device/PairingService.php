<?php

namespace App\Domains\Device;

use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * @return array{status: string, token?: string, device?: Device}
     */
    public function poll(string $code): array
    {
        $pairing = DevicePairingCode::query()->where('code', $code)->first();

        if (! $pairing || ($pairing->isExpired() && $pairing->claimed_at === null)) {
            throw ValidationException::withMessages([
                'code' => 'Pairing code is invalid or expired.',
            ]);
        }

        if ($pairing->isPending()) {
            return ['status' => 'pending'];
        }

        $device = $pairing->device()->firstOrFail();

        if (! $device->isPaired()) {
            return ['status' => 'pending'];
        }

        $token = $device->createToken('tv', ['device'])->plainTextToken;

        return [
            'status' => 'paired',
            'token' => $token,
            'device' => $device,
        ];
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
