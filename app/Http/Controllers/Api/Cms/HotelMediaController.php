<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\HotelBrandingService;
use App\Domains\Content\VideoSource;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HotelMediaController extends Controller
{
    public function store(Request $request, Hotel $hotel, HotelBrandingService $branding): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        if ($request->filled('url') && ! $request->hasFile('file')) {
            return $this->storeLink($request, $hotel, $branding);
        }

        $purpose = $request->input('purpose');

        $request->validate([
            'purpose' => ['required', 'string', Rule::in(['logo', 'background'])],
            'file' => [
                'required',
                'file',
                'max:51200',
                $purpose === 'logo'
                    ? 'mimes:jpg,jpeg,png,webp,svg'
                    : 'mimes:jpg,jpeg,png,webp,mp4,webm,mov',
            ],
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $isVideo = str_starts_with($mime, 'video/') || in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov'], true);

        if ($purpose === 'logo' && $isVideo) {
            abort(422, 'Logo phải là ảnh.');
        }

        $type = $purpose === 'logo' ? 'logo' : ($isVideo ? 'video' : 'image');
        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: ($isVideo ? 'mp4' : 'jpg');
        $path = $file->storeAs(
            'hotels/'.$hotel->id,
            $purpose.'-'.uniqid('', true).'.'.$ext,
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

        return $this->assign($hotel, $branding, $asset, (string) $purpose);
    }

    public function destroy(Request $request, Hotel $hotel, string $purpose, HotelBrandingService $branding): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $branding->clear($hotel, $purpose);
        $hotel->refresh()->load(['logo', 'defaultMedia']);

        return response()->json(['data' => HotelController::branding($hotel)]);
    }

    private function storeLink(Request $request, Hotel $hotel, HotelBrandingService $branding): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'string', Rule::in(['background'])],
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

        return $this->assign($hotel, $branding, $asset, 'background');
    }

    private function assign(Hotel $hotel, HotelBrandingService $branding, MediaAsset $asset, string $purpose): JsonResponse
    {
        $column = $purpose === 'logo' ? 'logo_media_id' : 'default_media_id';
        $hotel->load(['logo', 'defaultMedia']);
        $previous = $purpose === 'logo' ? $hotel->logo : $hotel->defaultMedia;
        $hotel->forceFill([$column => $asset->id])->save();

        if ($previous && $previous->id !== $asset->id) {
            $branding->deleteIfOrphaned($previous);
        }

        $branding->touchScreens($hotel);
        $hotel->refresh()->load(['logo', 'defaultMedia']);

        return response()->json(['data' => HotelController::branding($hotel)], 201);
    }
}
