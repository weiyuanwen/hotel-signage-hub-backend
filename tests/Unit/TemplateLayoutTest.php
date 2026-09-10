<?php

namespace Tests\Unit;

use App\Domains\Content\TemplateLayout;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateLayoutTest extends TestCase
{
    public function test_defaults_and_normalize_fill_gallery_font_and_slots(): void
    {
        $layout = TemplateLayout::normalize(null, 'linen');

        $this->assertSame('gallery', $layout['background']['source']);
        $this->assertSame('sunlit', $layout['background']['gallery_id']);
        $this->assertSame('be-vietnam', $layout['font']);
        $this->assertSame('soft', $layout['tone']);
        $this->assertArrayHasKey('name', $layout['slots']);
        $this->assertLessThanOrEqual(92, $layout['slots']['name']['x']);
    }

    public function test_normalize_strips_slogan_and_rejects_unknown_hex(): void
    {
        $layout = TemplateLayout::normalize([
            'slogan' => str_repeat('A', 90),
            'colors' => ['name' => 'red', 'slogan' => '#AABBCC', 'muted' => '#gg0000'],
            'slots' => ['name' => ['x' => 120, 'y' => -4]],
        ], 'dusk');

        $this->assertSame(80, mb_strlen($layout['slogan']));
        $this->assertSame('#aabbcc', $layout['colors']['slogan']);
        $this->assertNotSame('#gg0000', $layout['colors']['muted']);
        $this->assertSame(92, $layout['slots']['name']['x']);
        $this->assertSame(0, $layout['slots']['name']['y']);
    }

    public function test_normalize_clamps_font_sizes(): void
    {
        $layout = TemplateLayout::normalize([
            'sizes' => ['name' => 99, 'slogan' => 0.1, 'message' => 'nope', 'room' => 1.8],
        ], 'dusk');

        $this->assertSame(8.0, $layout['sizes']['name']);
        $this->assertSame(1.2, $layout['sizes']['slogan']);
        $this->assertSame(1.7, $layout['sizes']['message']);
        $this->assertSame(1.8, $layout['sizes']['room']);
        $this->assertSame(4.5, TemplateLayout::normalize(null, 'linen')['sizes']['name']);
        $this->assertSame(5.4, TemplateLayout::normalize(null, 'garden')['sizes']['name']);
    }

    public function test_assert_valid_rejects_unknown_tone_font_and_gallery(): void
    {
        $this->expectException(ValidationException::class);

        TemplateLayout::assertValid([
            'tone' => 'neon',
            'font' => 'comic',
            'background' => ['gallery_id' => 'not-a-photo'],
        ]);
    }

    public function test_resolve_background_prefers_upload_then_gallery(): void
    {
        $layout = TemplateLayout::normalize([
            'background' => ['source' => 'upload', 'gallery_id' => 'cafe'],
        ], 'harbor');

        $this->assertSame('https://cdn.example.test/upload.jpg', TemplateLayout::resolveBackgroundUrl(
            $layout,
            'https://cdn.example.test/upload.jpg',
        ));

        $gallery = TemplateLayout::normalize([
            'background' => ['source' => 'gallery', 'gallery_id' => 'cafe'],
        ], 'harbor');
        $this->assertStringContainsString('images.unsplash.com', (string) TemplateLayout::resolveBackgroundUrl($gallery, null));
    }
}
