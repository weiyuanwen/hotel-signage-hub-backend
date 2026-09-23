<?php

namespace App\Domains\Device;

use App\Domains\Auth\AccessTokenFactory;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\DevicePairingLink;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PairingService
{
    public function __construct(
        private PairingCodeGenerator $codes,
        private AccessTokenFactory $tokens,
    ) {}

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

            Cache::put($this->cacheKey($code), ['status' => 'pending'], 90);

            return ['device' => $device, 'code' => $pairing];
        });
    }

    /**
     * @return array{status: string, token?: string, device_id?: int, hotel_id?: int, room_id?: int}
     */
    public function poll(string $code): array
    {
        $cached = Cache::get($this->cacheKey($code));
        if (is_array($cached) && ($cached['status'] ?? null) === 'pending') {
            return ['status' => 'pending'];
        }

        if (is_array($cached) && ($cached['status'] ?? null) === 'paired') {
            return $this->issueTokenIfNeeded($code, $cached);
        }

        return $this->pollFromDatabase($code);
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

            if (! $hotel->subscriptionActive()) {
                throw ValidationException::withMessages([
                    'code' => 'Gói đã hết hạn. Gia hạn để tiếp tục dùng.',
                ]);
            }

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

            $fresh = $device->fresh() ?? $device;
            $this->rememberPaired($code, $fresh);

            return $fresh;
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

        if (! $hotel->subscriptionActive()) {
            throw ValidationException::withMessages([
                'room_id' => 'Gói đã hết hạn. Gia hạn để tiếp tục dùng.',
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

            if (! $hotel->subscriptionActive()) {
                throw ValidationException::withMessages([
                    'token' => 'Gói đã hết hạn. Gia hạn để tiếp tục dùng.',
                ]);
            }

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
                'token' => $this->tokens->device($device),
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

    /**
     * @param  array{status?: string, device_id?: int, hotel_id?: int, room_id?: int}  $hint
     * @return array{status: string, token?: string, device_id?: int, hotel_id?: int, room_id?: int}
     */
    private function issueTokenIfNeeded(string $code, array $hint): array
    {
        return DB::transaction(function () use ($code, $hint) {
            $pairing = DevicePairingCode::query()->where('code', $code)->lockForUpdate()->first();
            $device = $pairing?->device()->lockForUpdate()->first();

            if (! $pairing || ! $device || ! $device->isPaired()) {
                return $this->pairedPayload($hint);
            }

            $payload = $this->pairedPayload([
                'device_id' => $device->id,
                'hotel_id' => (int) $device->hotel_id,
                'room_id' => (int) $device->room_id,
            ]);

            if ($device->tokens()->exists()) {
                return $payload;
            }

            if ($pairing->claimed_at === null || $pairing->claimed_at->lt(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'code' => 'Pairing code is invalid or expired.',
                ]);
            }

            $payload['token'] = $this->tokens->device($device);

            return $payload;
        });
    }

    /**
     * @return array{status: string, token?: string, device_id?: int, hotel_id?: int, room_id?: int}
     */
    private function pollFromDatabase(string $code): array
    {
        $pairing = DevicePairingCode::query()->where('code', $code)->first();

        if (! $pairing || ($pairing->isExpired() && $pairing->claimed_at === null)) {
            throw ValidationException::withMessages([
                'code' => 'Pairing code is invalid or expired.',
            ]);
        }

        if ($pairing->isPending()) {
            Cache::put($this->cacheKey($code), ['status' => 'pending'], 90);

            return ['status' => 'pending'];
        }

        $device = $pairing->device;
        if (! $device || ! $device->isPaired()) {
            return ['status' => 'pending'];
        }

        $this->rememberPaired($code, $device);

        return $this->issueTokenIfNeeded($code, [
            'device_id' => $device->id,
            'hotel_id' => (int) $device->hotel_id,
            'room_id' => (int) $device->room_id,
        ]);
    }

    private function rememberPaired(string $code, Device $device): void
    {
        Cache::put($this->cacheKey($code), [
            'status' => 'paired',
            'device_id' => $device->id,
            'hotel_id' => (int) $device->hotel_id,
            'room_id' => (int) $device->room_id,
        ], 180);
    }

    /**
     * @param  array{device_id?: int, hotel_id?: int, room_id?: int}  $hint
     * @return array{status: string, device_id?: int, hotel_id?: int, room_id?: int}
     */
    private function pairedPayload(array $hint): array
    {
        $payload = ['status' => 'paired'];
        foreach (['device_id', 'hotel_id', 'room_id'] as $field) {
            if (isset($hint[$field])) {
                $payload[$field] = (int) $hint[$field];
            }
        }

        return $payload;
    }

    private function cacheKey(string $code): string
    {
        return 'pairing:'.$code;
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
