<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\StayService;
use App\Domains\Content\WelcomeTemplateKey;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StayController extends Controller
{
    public function __construct(private StayService $stays) {}

    public function checkIn(Request $request, Hotel $hotel, Room $room): JsonResponse
    {
        $this->assertRoomInHotel($hotel, $room);
        abort_unless($request->user()?->can('stays.manage'), 403);

        $data = $request->validate([
            'guest_display_name' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'max:8'],
            'source' => ['nullable', 'in:manual,pms'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', Rule::in(WelcomeTemplateKey::values())],
        ]);

        /** @var User $user */
        $user = $request->user();
        $stay = $this->stays->checkIn($room->load('hotel'), $user, $data);

        return response()->json(['data' => $stay], 201);
    }

    public function checkout(Request $request, Hotel $hotel, Room $room): JsonResponse
    {
        $this->assertRoomInHotel($hotel, $room);
        abort_unless($request->user()?->can('stays.manage'), 403);

        /** @var User $user */
        $user = $request->user();
        $this->stays->checkout($room, $user);

        return response()->json(['data' => ['checked_out' => true]]);
    }

    public function update(Request $request, Hotel $hotel, Room $room): JsonResponse
    {
        $this->assertRoomInHotel($hotel, $room);
        abort_unless($request->user()?->can('stays.manage'), 403);

        $data = $request->validate([
            'guest_display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'max:8'],
            'template_key' => ['sometimes', 'required', 'string', Rule::in(WelcomeTemplateKey::values())],
        ]);

        /** @var User $user */
        $user = $request->user();
        $stay = $this->stays->updateCurrent($room->load('currentWelcome'), $user, $data);

        return response()->json(['data' => $stay]);
    }

    private function assertRoomInHotel(Hotel $hotel, Room $room): void
    {
        abort_unless((int) $room->hotel_id === (int) $hotel->id, 404);
    }
}
