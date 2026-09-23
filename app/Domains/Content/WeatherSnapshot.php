<?php

namespace App\Domains\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WeatherSnapshot
{
    public const TTL_SECONDS = 900;

    /**
     * @return array{key: string, label: string, latitude: float, longitude: float, celsius?: int, code?: int}|null
     */
    public function for(?string $key): ?array
    {
        $place = WeatherRegion::get($key);
        if ($place === null) {
            return null;
        }

        if (! config('services.weather.fetch', true)) {
            return $place;
        }

        $cacheKey = 'weather:now:'.$place['key'];
        $cached = Cache::get($cacheKey);
        if ($this->isReading($cached)) {
            return [
                ...$place,
                'celsius' => (int) $cached['celsius'],
                'code' => (int) $cached['code'],
            ];
        }

        $now = $this->fetch($place);
        if ($now === null) {
            return $place;
        }

        Cache::put($cacheKey, $now, self::TTL_SECONDS);

        return [...$place, ...$now];
    }

    /**
     * @param  array{latitude: float, longitude: float}  $place
     * @return array{celsius: int, code: int}|null
     */
    private function fetch(array $place): ?array
    {
        try {
            $response = Http::timeout(3)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'current' => 'temperature_2m,weather_code',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $celsius = $response->json('current.temperature_2m');
        $code = $response->json('current.weather_code');
        if (! is_numeric($celsius) || ! is_numeric($code)) {
            return null;
        }

        return [
            'celsius' => (int) round((float) $celsius),
            'code' => (int) $code,
        ];
    }

    /**
     * @phpstan-assert-if-true array{celsius: int, code: int} $cached
     */
    private function isReading(mixed $cached): bool
    {
        return is_array($cached)
            && isset($cached['celsius'], $cached['code'])
            && is_numeric($cached['celsius'])
            && is_numeric($cached['code']);
    }
}
