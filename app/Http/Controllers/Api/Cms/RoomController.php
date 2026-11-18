<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Device\PairingService;
use App\Domains\Realtime\Events\DeviceCommandIssued;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Hotel $hotel): JsonResponse
    {
        $rooms = $hotel->rooms()
            ->with(['currentWelcome', 'defaultMedia'])
            ->withCount(['devices as paired_tv_count' => function ($query) {
                $query->where('status', 'paired');
            }])
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => self::present($room));

        return response()->json(['data' => $rooms]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Room $room): array
    {
        $room->loadMissing(['defaultMedia', 'currentWelcome']);
        $row = $room->toArray();
        unset($row['default_media']);
        $media = $room->defaultMedia;

        return [
            ...$row,
            'background_url' => $media?->url(),
            'background_kind' => $media?->type === 'video' ? 'video' : ($media ? 'image' : null),
        ];
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('rooms', 'code')->where('hotel_id', $hotel->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'in:guest,public'],
        ]);

        $room = $hotel->rooms()->create([
            'code' => $data['code'],
            'name' => $data['name'] ?? null,
            'kind' => $data['kind'] ?? 'guest',
            'is_active' => true,
        ]);

        return response()->json(['data' => self::present($room)], 201);
    }

    public function update(Request $request, Hotel $hotel, Room $room): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);
        abort_unless((int) $room->hotel_id === (int) $hotel->id, 404);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('rooms', 'code')->where('hotel_id', $hotel->id)->ignore($room->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'in:guest,public'],
        ]);

        $codeChanged = $data['code'] !== $room->code || ($data['kind'] ?? $room->kind) !== $room->kind;

        $room->forceFill([
            'code' => $data['code'],
            'name' => $data['name'] ?? null,
            'kind' => $data['kind'] ?? $room->kind,
        ])->save();

        if ($codeChanged) {
            $room->bumpRevision();
            foreach ($room->devices()->where('status', 'paired')->get() as $device) {
                event(new DeviceCommandIssued($device, 'reload'));
            }
        }

        return response()->json(['data' => self::present($room)]);
    }

    public function destroy(Request $request, Hotel $hotel, Room $room, PairingService $pairing): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);
        abort_unless((int) $room->hotel_id === (int) $hotel->id, 404);

        DB::transaction(function () use ($room, $pairing) {
            foreach ($room->devices()->where('status', 'paired')->get() as $device) {
                $pairing->unpair($device);
            }
            $room->devices()->update(['room_id' => null]);
            $room->forceFill(['current_welcome_id' => null])->save();
            $room->welcomes()->delete();
            $room->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }
}
