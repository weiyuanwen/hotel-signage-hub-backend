<?php

namespace App\Domains\Content;

use App\Models\Hotel;
use App\Models\HotelWelcomeTemplate;

class WelcomeTemplateCatalog
{
    public function syncHotel(Hotel $hotel): void
    {
        foreach (WelcomeTemplateKey::cases() as $key) {
            HotelWelcomeTemplate::query()->firstOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'template_key' => $key->value,
                ],
                [
                    'is_enabled' => true,
                    'display_name' => null,
                    'sort_order' => $key->sortOrder(),
                ],
            );
        }
    }

    public function isEnabled(Hotel $hotel, string $key): bool
    {
        $this->syncHotel($hotel);

        return HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $key)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * @return array{
     *     default_key: string,
     *     templates: list<array{
     *         key: string,
     *         built_in_name: string,
     *         display_name: ?string,
     *         label: string,
     *         is_enabled: bool,
     *         sort_order: int
     *     }>
     * }
     */
    public function toPayload(Hotel $hotel, bool $includeDisabled): array
    {
        $this->syncHotel($hotel);
        $hotel->refresh();

        $query = HotelWelcomeTemplate::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order');

        if (! $includeDisabled) {
            $query->where('is_enabled', true);
        }

        $templates = $query->with('backgroundMedia')->get()
            ->map(function (HotelWelcomeTemplate $template): array {
                $key = WelcomeTemplateKey::from($template->template_key);
                $layout = TemplateLayout::normalize($template->layout, $template->template_key);

                return [
                    'key' => $template->template_key,
                    'built_in_name' => $key->builtInLabel(),
                    'display_name' => $template->display_name,
                    'label' => $template->label(),
                    'is_enabled' => $template->is_enabled,
                    'sort_order' => $template->sort_order,
                    'layout' => $layout,
                    'background_url' => TemplateLayout::resolveBackgroundUrl(
                        $layout,
                        $template->backgroundMedia?->url(),
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'default_key' => $hotel->default_welcome_template_key,
            'templates' => $templates,
        ];
    }
}
