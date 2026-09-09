<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Content\WelcomeTemplateCatalog;
use App\Domains\Content\WelcomeTemplateKey;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WelcomeTemplateController extends Controller
{
    public function __construct(private WelcomeTemplateCatalog $catalog) {}

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('rooms.view'), 403);

        $includeDisabled = $request->user()?->can('templates.manage') ?? false;

        return response()->json([
            'data' => $this->catalog->toPayload($hotel, $includeDisabled),
        ]);
    }

    public function update(Request $request, Hotel $hotel, string $template): JsonResponse
    {
        abort_unless($request->user()?->can('templates.manage'), 403);
        $this->catalog->syncHotel($hotel);

        if (WelcomeTemplateKey::tryFrom($template) === null) {
            abort(404);
        }

        $data = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);

        $row = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $template)
            ->firstOrFail();

        if (array_key_exists('display_name', $data) && $data['display_name'] === '') {
            $data['display_name'] = null;
        }

        if (array_key_exists('is_enabled', $data) && $data['is_enabled'] === false) {
            if ($hotel->default_welcome_template_key === $template) {
                throw ValidationException::withMessages([
                    'is_enabled' => 'Không thể tắt mẫu mặc định.',
                ]);
            }
            $enabled = HotelWelcomeTemplate::query()
                ->where('hotel_id', $hotel->id)
                ->where('is_enabled', true)
                ->count();
            if ($enabled <= 1 && $row->is_enabled) {
                throw ValidationException::withMessages([
                    'is_enabled' => 'Không thể tắt mẫu cuối cùng.',
                ]);
            }
        }

        $row->fill($data);
        $row->save();

        return response()->json(['data' => $this->catalog->toPayload($hotel, true)]);
    }

    public function updateDefault(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('templates.manage'), 403);
        $this->catalog->syncHotel($hotel);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(WelcomeTemplateKey::values())],
        ]);

        if (! $this->catalog->isEnabled($hotel, $data['key'])) {
            throw ValidationException::withMessages([
                'key' => 'Mẫu mặc định phải đang bật.',
            ]);
        }

        $hotel->forceFill(['default_welcome_template_key' => $data['key']])->save();

        return response()->json(['data' => $this->catalog->toPayload($hotel->fresh(), true)]);
    }
}
