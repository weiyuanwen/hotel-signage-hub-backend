<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Billing\HotelPlan;
use App\Domains\Content\HotelBrandingService;
use App\Domains\Content\WeatherRegion;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HotelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = Hotel::query()
            ->where('is_active', true)
            ->withCount(['devices as paired_device_count' => fn ($devices) => $devices->where('status', 'paired')])
            ->orderBy('name');

        if (! $user->hasRole('super-admin')) {
            $query->whereIn('id', $user->hotels()->pluck('hotels.id'));
        }

        return response()->json([
            'data' => $query->get(['id', 'name', 'slug', 'default_locale', 'plan', 'device_limit'])
                ->map(fn (Hotel $hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'slug' => $hotel->slug,
                    'default_locale' => $hotel->default_locale,
                    ...$hotel->toPlanPayload(),
                ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('hotels.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:hotels,slug'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'default_locale' => ['nullable', 'string', 'max:8'],
        ]);

        $hotel = Hotel::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh',
            'default_locale' => $data['default_locale'] ?? 'vi',
            'weather_region' => 'ho-chi-minh',
            'is_active' => true,
            'plan' => HotelPlan::PREMIUM,
            'device_limit' => HotelPlan::deviceLimit(HotelPlan::PREMIUM),
        ]);

        return response()->json(['data' => $hotel], 201);
    }

    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.view'), 403);

        $hotel->load(['logo', 'defaultMedia']);

        return response()->json(['data' => $this->branding($hotel)]);
    }

    public function update(Request $request, Hotel $hotel, HotelBrandingService $branding): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.manage'), 403);

        $data = $request->validate([
            'weather_region' => ['sometimes', 'required', 'string', Rule::in(WeatherRegion::keys())],
            'wifi_ssid' => ['sometimes', 'nullable', 'string', 'max:64'],
            'wifi_password' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        if ($data === []) {
            return response()->json(['data' => $this->branding($hotel->load(['logo', 'defaultMedia']))]);
        }

        if (array_key_exists('wifi_ssid', $data) && $data['wifi_ssid'] === '') {
            $data['wifi_ssid'] = null;
        }
        if (array_key_exists('wifi_password', $data) && $data['wifi_password'] === '') {
            $data['wifi_password'] = null;
        }
        if (array_key_exists('wifi_ssid', $data) && $data['wifi_ssid'] === null) {
            $data['wifi_password'] = null;
        }

        $hotel->forceFill($data)->save();
        $branding->touchScreens($hotel);
        $hotel->refresh()->load(['logo', 'defaultMedia']);

        return response()->json(['data' => $this->branding($hotel)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function branding(Hotel $hotel): array
    {
        $background = $hotel->defaultMedia;

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            ...$hotel->toPlanPayload(),
            'weather_region' => $hotel->weather_region,
            'weather' => WeatherRegion::get($hotel->weather_region),
            'logo_url' => $hotel->logo?->url(),
            'background_url' => $background?->url(),
            'background_kind' => $background?->type === 'video' ? 'video' : ($background ? 'image' : null),
            'wifi_ssid' => $hotel->wifi_ssid,
            'wifi_password' => $hotel->wifi_password,
        ];
    }
}
