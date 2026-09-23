<?php

namespace Tests\Unit;

use App\Domains\Content\WeatherSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherSnapshotTest extends TestCase
{
    public function test_fetches_once_and_reuses_cache(): void
    {
        config(['services.weather.fetch' => true]);
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 29.4, 'weather_code' => 2],
            ]),
        ]);

        $weather = app(WeatherSnapshot::class);

        $first = $weather->for('da-nang');
        $this->assertSame(29, $first['celsius'] ?? null);
        $this->assertSame(2, $first['code'] ?? null);

        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 1, 'weather_code' => 99],
            ]),
        ]);

        $second = $weather->for('da-nang');
        $this->assertSame(29, $second['celsius'] ?? null);
        $this->assertSame(2, $second['code'] ?? null);
        $this->assertNotEmpty(Cache::get('weather:now:da-nang'));
    }

    public function test_skips_fetch_when_disabled(): void
    {
        config(['services.weather.fetch' => false]);
        Http::fake();

        $place = app(WeatherSnapshot::class)->for('hue');

        $this->assertSame('hue', $place['key'] ?? null);
        $this->assertArrayNotHasKey('celsius', $place ?? []);
        Http::assertNothingSent();
    }
}
