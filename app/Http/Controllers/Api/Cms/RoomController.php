<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Hotel $hotel): JsonResponse
    {
        $rooms = $hotel->rooms()
            ->with('currentWelcome')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $rooms]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
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
