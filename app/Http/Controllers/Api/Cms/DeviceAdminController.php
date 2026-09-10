<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Device\HeartbeatService;
use App\Domains\Device\PairingService;
use App\Domains\Device\ScreenDataBuilder;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceAdminController extends Controller
{
    public function __construct(
        private PairingService $pairing,
        private HeartbeatService $heartbeat,
        private ScreenDataBuilder $screens,
    ) {}

    public function index(Hotel $hotel): JsonResponse
    {
        $devices = Device::query()
            ->where('hotel_id', $hotel->id)
            ->with(['hotel.logo', 'hotel.defaultMedia', 'room.currentWelcome', 'room.defaultMedia'])
            ->orderBy('id')
            ->get()
            ->map(fn (Device $device) => $this->payload($device));

        return response()->json(['data' => $devices]);
    }

    public function update(Request $request, Hotel $hotel, Device $device): JsonResponse
    {
        abort_unless($request->user()?->can('devices.pair'), 403);
        abort_unless((int) $device->hotel_id === (int) $hotel->id, 404);
        abort_unless($device->isPaired(), 422);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'room_id' => [
                'sometimes',
                'integer',
                Rule::exists('rooms', 'id')->where('hotel_id', $hotel->id),
            ],
        ]);

        if (array_key_exists('name', $data) && $data['name'] === '') {
            $data['name'] = null;
        }

        if (array_key_exists('room_id', $data)) {
            $room = Room::query()->where('hotel_id', $hotel->id)->findOrFail($data['room_id']);
            if (! $room->is_active) {
                throw ValidationException::withMessages([
                    'room_id' => 'Phòng không còn hoạt động.',
                ]);
            }
        }

        $roomChanged = array_key_exists('room_id', $data) && (int) $data['room_id'] !== (int) $device->room_id;

        $device->forceFill($data)->save();
        $device->refresh()->load(['hotel.logo', 'hotel.defaultMedia', 'room.currentWelcome', 'room.defaultMedia']);

        if ($roomChanged) {
            event(new DeviceCommandIssued($device, 'reload'));
        }

        return response()->json(['data' => $this->payload($device)]);
    }

    public function unpair(Request $request, Hotel $hotel, Device $device): JsonResponse
    {
        abort_unless($request->user()?->can('devices.manage'), 403);
        abort_unless((int) $device->hotel_id === (int) $hotel->id, 404);

        $this->pairing->unpair($device);

        return response()->json(['data' => ['unpaired' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Device $device): array
    {
        $row = $device->toArray();
        unset($row['room'], $row['hotel']);

        $screen = $device->isPaired() && $device->hotel && $device->room
            ? $this->screens->forRoom($device->hotel, $device->room)
            : null;

        return [
            ...$row,
            'room_code' => $device->room?->code,
            'room_name' => $device->room?->name,
            'room_kind' => $device->room?->kind,
            'room_guest' => $device->room?->currentWelcome?->guest_display_name,
            'online' => $this->heartbeat->isOnline($device->id),
            'screen' => $screen,
        ];
    }
}
