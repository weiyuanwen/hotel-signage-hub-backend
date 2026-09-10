<?php

namespace App\Domains\Content;

use Illuminate\Validation\ValidationException;

class TemplateLayout
{
    /** @return list<string> */
    public static function tones(): array
    {
        return ['neutral', 'warm', 'cool', 'soft', 'contrast'];
    }

    /** @return list<string> */
    public static function fonts(): array
    {
        return ['geist', 'be-vietnam', 'outfit', 'cormorant'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(string $key): array
    {
        $galleryId = TemplateGallery::defaultId($key);
        $colors = match ($key) {
            'linen' => ['name' => '#3a2a1c', 'slogan' => '#5a4634', 'muted' => '#6b5a4a'],
            'garden' => ['name' => '#243028', 'slogan' => '#3d4a3e', 'muted' => '#5a665c'],
            'harbor' => ['name' => '#f4efe4', 'slogan' => '#e4ddd0', 'muted' => '#c8c2b4'],
            'stone' => ['name' => '#f3eadc', 'slogan' => '#e2d6c4', 'muted' => '#c4b8a6'],
            default => ['name' => '#f4efe6', 'slogan' => '#e8dfd0', 'muted' => '#cfc4b4'],
        };
        $slots = match ($key) {
            'harbor' => [
                'logo' => ['x' => 8, 'y' => 16, 'visible' => true],
                'name' => ['x' => 8, 'y' => 38],
                'slogan' => ['x' => 8, 'y' => 54, 'visible' => true],
                'message' => ['x' => 8, 'y' => 64, 'visible' => true],
                'room' => ['x' => 86, 'y' => 88],
            ],
            'stone' => [
                'logo' => ['x' => 8, 'y' => 14, 'visible' => true],
                'name' => ['x' => 8, 'y' => 62],
                'slogan' => ['x' => 8, 'y' => 76, 'visible' => true],
                'message' => ['x' => 8, 'y' => 84, 'visible' => true],
                'room' => ['x' => 86, 'y' => 88],
            ],
            'linen' => [
                'logo' => ['x' => 42, 'y' => 18, 'visible' => true],
                'name' => ['x' => 22, 'y' => 42],
                'slogan' => ['x' => 22, 'y' => 58, 'visible' => true],
                'message' => ['x' => 22, 'y' => 68, 'visible' => true],
                'room' => ['x' => 46, 'y' => 88],
            ],
            default => [
                'logo' => ['x' => 8, 'y' => 16, 'visible' => true],
                'name' => ['x' => 8, 'y' => 40],
                'slogan' => ['x' => 8, 'y' => 56, 'visible' => true],
                'message' => ['x' => 8, 'y' => 66, 'visible' => true],
                'room' => ['x' => 86, 'y' => 88],
            ],
        };

        return [
            'background' => [
                'source' => 'gallery',
                'gallery_id' => $galleryId,
            ],
            'tone' => match ($key) {
                'linen' => 'soft',
                'harbor' => 'cool',
                'garden' => 'warm',
                'stone' => 'contrast',
                default => 'warm',
            },
            'font' => match ($key) {
                'linen' => 'be-vietnam',
                'harbor' => 'outfit',
                'garden' => 'cormorant',
                'stone' => 'cormorant',
                default => 'geist',
            },
            'colors' => $colors,
            'sizes' => self::defaultSizes($key),
            'slogan' => '',
            'slots' => $slots,
        ];
    }

    /**
     * @return array{name: float, slogan: float, message: float, room: float}
     */
    public static function defaultSizes(string $key): array
    {
        return [
            'name' => ($key === 'garden' || $key === 'stone') ? 5.4 : 4.5,
            'slogan' => 2.2,
            'message' => 1.7,
            'room' => 1.4,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>
     */
    public static function normalize(?array $raw, string $key): array
    {
        $base = self::defaults($key);
        if (! is_array($raw) || $raw === []) {
            return $base;
        }

        $tone = is_string($raw['tone'] ?? null) && in_array($raw['tone'], self::tones(), true)
            ? $raw['tone']
            : $base['tone'];
        $font = is_string($raw['font'] ?? null) && in_array($raw['font'], self::fonts(), true)
            ? $raw['font']
            : $base['font'];
        $slogan = is_string($raw['slogan'] ?? null) ? mb_substr(trim($raw['slogan']), 0, 80) : $base['slogan'];

        $bg = is_array($raw['background'] ?? null) ? $raw['background'] : [];
        $source = ($bg['source'] ?? '') === 'upload' ? 'upload' : 'gallery';
        $galleryId = is_string($bg['gallery_id'] ?? null) ? $bg['gallery_id'] : $base['background']['gallery_id'];
        if (TemplateGallery::url($galleryId) === null) {
            $galleryId = $base['background']['gallery_id'];
        }

        $colorsIn = is_array($raw['colors'] ?? null) ? $raw['colors'] : [];
        $colors = [
            'name' => self::color($colorsIn['name'] ?? null, $base['colors']['name']),
            'slogan' => self::color($colorsIn['slogan'] ?? null, $base['colors']['slogan']),
            'muted' => self::color($colorsIn['muted'] ?? null, $base['colors']['muted']),
        ];

        $sizesIn = is_array($raw['sizes'] ?? null) ? $raw['sizes'] : [];
        $sizeBase = $base['sizes'];
        $sizes = [
            'name' => self::size($sizesIn['name'] ?? null, $sizeBase['name'], 2.5, 8.0),
            'slogan' => self::size($sizesIn['slogan'] ?? null, $sizeBase['slogan'], 1.2, 4.5),
            'message' => self::size($sizesIn['message'] ?? null, $sizeBase['message'], 1.0, 3.5),
            'room' => self::size($sizesIn['room'] ?? null, $sizeBase['room'], 0.8, 3.0),
        ];

        $slotsIn = is_array($raw['slots'] ?? null) ? $raw['slots'] : [];
        $slots = [
            'logo' => self::slot($slotsIn['logo'] ?? null, $base['slots']['logo'], true),
            'name' => self::slot($slotsIn['name'] ?? null, $base['slots']['name'], false),
            'slogan' => self::slot($slotsIn['slogan'] ?? null, $base['slots']['slogan'], true),
            'message' => self::slot($slotsIn['message'] ?? null, $base['slots']['message'], true),
            'room' => self::slot($slotsIn['room'] ?? null, $base['slots']['room'], false),
        ];

        return [
            'background' => [
                'source' => $source,
                'gallery_id' => $galleryId,
            ],
            'tone' => $tone,
            'font' => $font,
            'colors' => $colors,
            'sizes' => $sizes,
            'slogan' => $slogan,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function assertValid(array $raw): array
    {
        $errors = [];
        if (isset($raw['tone']) && ! in_array($raw['tone'], self::tones(), true)) {
            $errors['layout.tone'] = 'Tông màu không hợp lệ.';
        }
        if (isset($raw['font']) && ! in_array($raw['font'], self::fonts(), true)) {
            $errors['layout.font'] = 'Font không hợp lệ.';
        }
        $bg = is_array($raw['background'] ?? null) ? $raw['background'] : [];
        if (isset($bg['gallery_id']) && TemplateGallery::url((string) $bg['gallery_id']) === null) {
            $errors['layout.background.gallery_id'] = 'Ảnh gợi ý không có trong thư viện.';
        }
        if (isset($bg['source']) && ! in_array($bg['source'], ['gallery', 'upload'], true)) {
            $errors['layout.background.source'] = 'Nguồn ảnh không hợp lệ.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $raw;
    }

    public static function resolveBackgroundUrl(array $layout, ?string $uploadUrl): ?string
    {
        if (($layout['background']['source'] ?? 'gallery') === 'upload' && is_string($uploadUrl) && $uploadUrl !== '') {
            return $uploadUrl;
        }

        $id = $layout['background']['gallery_id'] ?? TemplateGallery::defaultId('dusk');

        return TemplateGallery::url(is_string($id) ? $id : TemplateGallery::defaultId('dusk'));
    }

    private static function color(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }
        $value = trim($value);
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value) !== 1) {
            return $fallback;
        }

        return strtolower($value);
    }

    private static function size(mixed $value, float $fallback, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }
        $n = max($min, min($max, round((float) $value, 1)));

        return fmod($n, 1.0) === 0.0 ? (float) (int) $n : $n;
    }

    /**
     * @param  array{x: float|int, y: float|int, visible?: bool}  $fallback
     * @return array{x: float, y: float, visible?: bool}
     */
    private static function slot(mixed $value, array $fallback, bool $withVisible): array
    {
        $x = is_array($value) && is_numeric($value['x'] ?? null) ? (float) $value['x'] : (float) $fallback['x'];
        $y = is_array($value) && is_numeric($value['y'] ?? null) ? (float) $value['y'] : (float) $fallback['y'];
        $slot = [
            'x' => self::coord($x),
            'y' => self::coord($y),
        ];
        if ($withVisible) {
            $visible = is_array($value) && array_key_exists('visible', $value)
                ? (bool) $value['visible']
                : (bool) ($fallback['visible'] ?? true);
            $slot['visible'] = $visible;
        }

        return $slot;
    }

    private static function coord(float $value): int|float
    {
        $n = max(0, min(92, round($value, 1)));

        return fmod($n, 1.0) === 0.0 ? (int) $n : $n;
    }
}
