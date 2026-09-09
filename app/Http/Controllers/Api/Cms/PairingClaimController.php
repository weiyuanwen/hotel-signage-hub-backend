<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Device\PairingService;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PairingClaimController extends Controller
{
    public function __construct(private PairingService $pairing) {}

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('devices.pair'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
            'room_id' => ['required', 'integer'],
        ]);

        $room = Room::query()->where('hotel_id', $hotel->id)->findOrFail($data['room_id']);

        /** @var User $user */
        $user = $request->user();
        $device = $this->pairing->claim(strtoupper($data['code']), $room, $user);

        return response()->json(['data' => $device]);
    }
}
