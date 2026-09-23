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
        $this->assertSame('Chào mừng quý khách', $layout['lead']);
        $this->assertSame('Chúc quý khách có những trải nghiệm tuyệt vời.', $layout['wish']);
    }

    public function test_normalize_keeps_lead_and_wish_and_clamps_length(): void
    {
        $empty = TemplateLayout::normalize([
            'lead' => '  ',
            'wish' => '',
        ], 'dusk');
        $this->assertSame('', $empty['lead']);
        $this->assertSame('', $empty['wish']);

        $layout = TemplateLayout::normalize([
            'lead' => 'Chào mừng đến nhà.',
            'wish' => str_repeat('B', 160),
        ], 'dusk');
        $this->assertSame('Chào mừng đến nhà.', $layout['lead']);
        $this->assertSame(120, mb_strlen($layout['wish']));
        $this->assertSame('Chào mừng quý khách', TemplateLayout::normalize(null, 'linen')['lead']);
        $this->assertSame('Chúc quý khách có những trải nghiệm tuyệt vời.', TemplateLayout::normalize(null, 'linen')['wish']);
    }

    public function test_normalize_clamps_font_sizes(): void
    {
        $layout = TemplateLayout::normalize([
            'sizes' => [
                'name' => 99,
                'slogan' => 0.1,
                'message' => 'nope',
                'room' => 1.8,
                'wifi' => 9,
                'wifiPassword' => 0.1,
                'time' => 0.2,
                'clock' => 8,
                'weather' => 9,
                'logo' => 99,
            ],
        ], 'dusk');

        $this->assertSame(8.0, $layout['sizes']['name']);
        $this->assertSame(1.2, $layout['sizes']['slogan']);
        $this->assertSame(1.7, $layout['sizes']['message']);
        $this->assertSame(1.8, $layout['sizes']['room']);
        $this->assertSame(3.5, $layout['sizes']['wifi']);
        $this->assertSame(0.6, $layout['sizes']['wifiPassword']);
        $this->assertSame(1.5, $layout['sizes']['time']);
        $this->assertSame(2.4, $layout['sizes']['clock']);
        $this->assertSame(6.0, $layout['sizes']['weather']);
        $this->assertSame(28.0, $layout['sizes']['logo']);
        $this->assertSame(4.0, TemplateLayout::normalize(['sizes' => ['logo' => 0.2]], 'dusk')['sizes']['logo']);
        $this->assertSame(11.0, TemplateLayout::normalize(null, 'dusk')['sizes']['logo']);
        $this->assertSame(4.5, TemplateLayout::normalize(null, 'linen')['sizes']['name']);
        $this->assertSame(5.4, TemplateLayout::normalize(null, 'garden')['sizes']['name']);
        $this->assertSame(1.5, TemplateLayout::normalize(null, 'dusk')['sizes']['wifi']);
        $this->assertSame(1.2, TemplateLayout::normalize(null, 'dusk')['sizes']['wifiPassword']);
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
        $this->assertStringContainsString('landing/gallery/cafe.jpg', (string) TemplateLayout::resolveBackgroundUrl($gallery, null));
    }

    public function test_vista_defaults_to_split_suite_and_cormorant(): void
    {
        $layout = TemplateLayout::normalize(null, 'vista');

        $this->assertSame('split', $layout['composition']);
        $this->assertSame('suite', $layout['background']['gallery_id']);
        $this->assertSame('cormorant', $layout['font']);
        $this->assertSame('contrast', $layout['tone']);
        $this->assertSame('#d4b07a', $layout['colors']['name']);
        $this->assertSame(3.8, $layout['sizes']['name']);
        $this->assertSame(8.0, $layout['sizes']['logo']);
        $this->assertSame('Chúng tôi rất hân hạnh chào đón quý khách.', $layout['lead']);
        $this->assertSame('Chúc quý khách kỳ nghỉ thư thái và đáng nhớ.', $layout['wish']);
        $this->assertSame('split', TemplateLayout::normalize(['composition' => 'wide'], 'vista')['composition']);
        $this->assertSame('full', TemplateLayout::normalize(['composition' => 'full'], 'vista')['composition']);
    }
}
