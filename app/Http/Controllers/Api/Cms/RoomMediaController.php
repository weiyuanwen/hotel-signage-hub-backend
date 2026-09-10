<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\HotelBrandingService;
use App\Domains\Content\VideoSource;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomMediaController extends Controller
{
    public function store(Request $request, Hotel $hotel, Room $room, HotelBrandingService $branding): JsonResponse
    {
        $this->assertRoomInHotel($hotel, $room);
        abort_unless($request->user()?->can('rooms.manage'), 403);

        if ($request->filled('url') && ! $request->hasFile('file')) {
            return $this->storeLink($request, $hotel, $room, $branding);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,webp,mp4,webm,mov'],
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/') || in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov'], true);
        $type = $isVideo ? 'video' : 'image';
        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: ($isVideo ? 'mp4' : 'jpg');
        $path = $file->storeAs(
            'hotels/'.$hotel->id.'/rooms/'.$room->id,
            'background-'.uniqid('', true).'.'.$ext,
            'public',
        );

        $asset = MediaAsset::query()->create([
            'hotel_id' => $hotel->id,
            'type' => $type,
            'disk' => 'public',
            'path' => $path,
            'mime' => $mime,
            'bytes' => $file->getSize(),
        ]);

        $branding->assignRoomBackground($room, $asset);

        return response()->json(['data' => RoomController::present($room->fresh(['defaultMedia', 'currentWelcome']))], 201);
    }

    public function destroy(Request $request, Hotel $hotel, Room $room, HotelBrandingService $branding): JsonResponse
    {
        $this->assertRoomInHotel($hotel, $room);
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $branding->clearRoomBackground($room);

        return response()->json(['data' => RoomController::present($room->fresh(['defaultMedia', 'currentWelcome']))]);
    }

    private function storeLink(Request $request, Hotel $hotel, Room $room, HotelBrandingService $branding): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $parsed = VideoSource::parse($data['url']);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'url' => 'Dùng link YouTube, Vimeo, hoặc file .mp4/.webm.',
            ]);
        }

        $asset = MediaAsset::query()->create([
            'hotel_id' => $hotel->id,
            'type' => 'video',
            'disk' => 'external',
            'path' => $parsed['url'],
            'mime' => $parsed['mime'],
            'bytes' => 0,
        ]);

        $branding->assignRoomBackground($room, $asset);

        return response()->json(['data' => RoomController::present($room->fresh(['defaultMedia', 'currentWelcome']))], 201);
    }

    private function assertRoomInHotel(Hotel $hotel, Room $room): void
    {
        abort_unless((int) $room->hotel_id === (int) $hotel->id, 404);
    }
}
