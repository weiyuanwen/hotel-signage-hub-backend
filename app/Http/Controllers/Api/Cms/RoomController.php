<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $room], 201);
    }
}
