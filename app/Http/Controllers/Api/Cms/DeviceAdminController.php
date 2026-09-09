<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Device\HeartbeatService;
use App\Domains\Device\PairingService;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceAdminController extends Controller
{
    public function __construct(
        private PairingService $pairing,
        private HeartbeatService $heartbeat,
    ) {}

    public function index(Hotel $hotel): JsonResponse
    {
        $devices = Device::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Device $device) => [
                ...$device->toArray(),
                'online' => $this->heartbeat->isOnline($device->id),
            ]);

        return response()->json(['data' => $devices]);
    }

    public function unpair(Request $request, Hotel $hotel, Device $device): JsonResponse
    {
        abort_unless($request->user()?->can('devices.manage'), 403);
        abort_unless((int) $device->hotel_id === (int) $hotel->id, 404);

        $this->pairing->unpair($device);

        return response()->json(['data' => ['unpaired' => true]]);
    }
}
