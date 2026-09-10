<?php

namespace App\Domains\Content;

final class WeatherRegion
{
    /**
     * @return array<string, array{key: string, label: string, latitude: float, longitude: float}>
     */
    public static function all(): array
    {
        $rows = [
            'ha-noi' => ['Hà Nội', 21.0278, 105.8342],
            'ha-long' => ['Hạ Long', 20.9101, 107.1839],
            'hai-phong' => ['Hải Phòng', 20.8449, 106.6881],
            'ninh-binh' => ['Ninh Bình', 20.2506, 105.9744],
            'thanh-hoa' => ['Thanh Hóa', 19.8067, 105.7852],
            'sam-son' => ['Sầm Sơn', 19.7464, 105.9053],
            'vinh' => ['Vinh', 18.6796, 105.6813],
            'hue' => ['Huế', 16.4637, 107.5909],
            'da-nang' => ['Đà Nẵng', 16.0544, 108.2022],
            'hoi-an' => ['Hội An', 15.8801, 108.3380],
            'nha-trang' => ['Nha Trang', 12.2388, 109.1967],
            'da-lat' => ['Đà Lạt', 11.9404, 108.4583],
            'phan-thiet' => ['Phan Thiết', 10.9287, 108.1021],
            'vung-tau' => ['Vũng Tàu', 10.3460, 107.0843],
            'ho-chi-minh' => ['TP. Hồ Chí Minh', 10.7769, 106.7009],
            'can-tho' => ['Cần Thơ', 10.0452, 105.7469],
            'phu-quoc' => ['Phú Quốc', 10.2899, 103.9840],
        ];

        $out = [];
        foreach ($rows as $key => [$label, $lat, $lng]) {
            $out[$key] = [
                'key' => $key,
                'label' => $label,
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{key: string, label: string, latitude: float, longitude: float}|null
     */
    public static function get(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::all()[$key] ?? null;
    }
}
