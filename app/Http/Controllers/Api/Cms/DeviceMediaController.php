<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\HotelBrandingService;
use App\Domains\Content\VideoSource;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Hotel;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceMediaController extends Controller
{
    public function store(Request $request, Hotel $hotel, Device $device, HotelBrandingService $branding): JsonResponse
    {
        $this->assertDeviceInHotel($hotel, $device);
        abort_unless($request->user()?->can('devices.pair'), 403);
        $this->assertPaidPlan($hotel);

        if ($request->filled('url') && ! $request->hasFile('file')) {
            return $this->storeLink($request, $hotel, $device, $branding);
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
            'hotels/'.$hotel->id.'/devices/'.$device->id,
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

        $branding->assignDeviceBackground($device, $asset);

        return response()->json(['data' => $this->present($device->fresh(['defaultMedia', 'hotel', 'room']))], 201);
    }

    public function destroy(Request $request, Hotel $hotel, Device $device, HotelBrandingService $branding): JsonResponse
    {
        $this->assertDeviceInHotel($hotel, $device);
        abort_unless($request->user()?->can('devices.pair'), 403);
        $this->assertPaidPlan($hotel);

        $branding->clearDeviceBackground($device);

        return response()->json(['data' => $this->present($device->fresh(['defaultMedia', 'hotel', 'room']))]);
    }

    private function storeLink(Request $request, Hotel $hotel, Device $device, HotelBrandingService $branding): JsonResponse
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

        $branding->assignDeviceBackground($device, $asset);

        return response()->json(['data' => $this->present($device->fresh(['defaultMedia', 'hotel', 'room']))], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Device $device): array
    {
        $media = $device->defaultMedia;

        return [
            'id' => $device->id,
            'name' => $device->name,
            'room_id' => $device->room_id,
            'background_url' => $media?->url(),
            'background_kind' => $media ? ($media->type === 'video' ? 'video' : 'image') : null,
        ];
    }

    private function assertDeviceInHotel(Hotel $hotel, Device $device): void
    {
        abort_unless((int) $device->hotel_id === (int) $hotel->id, 404);
        abort_unless($device->isPaired(), 422);
    }

    private function assertPaidPlan(Hotel $hotel): void
    {
        if (! $hotel->allowsDeviceBackgrounds()) {
            throw ValidationException::withMessages([
                'plan' => 'Nền riêng từng TV dành cho gói Plus và Cao cấp.',
            ]);
        }
    }
}
