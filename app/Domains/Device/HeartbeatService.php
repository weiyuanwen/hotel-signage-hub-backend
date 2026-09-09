<?php

namespace App\Domains\Device;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;

class HeartbeatService
{
    public const TTL_SECONDS = 90;

    public function touch(Device $device): void
    {
        Cache::put($this->key($device->id), now()->toIso8601String(), self::TTL_SECONDS);
    }

    public function isOnline(int $deviceId): bool
    {
        return Cache::has($this->key($deviceId));
    }

    public function lastHeartbeatAt(int $deviceId): ?string
    {
        $value = Cache::get($this->key($deviceId));

        return is_string($value) ? $value : null;
    }

    public function flushLastSeen(Device $device): void
    {
        $seenAt = $this->lastHeartbeatAt($device->id);

        if ($seenAt === null) {
            return;
        }

        $device->forceFill(['last_seen_at' => $seenAt])->save();
    }

    private function key(int $deviceId): string
    {
        return "device:{$deviceId}:hb";
    }
}
