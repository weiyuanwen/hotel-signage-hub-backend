<?php

namespace Tests\Unit;

use App\Domains\Content\VideoSource;
use PHPUnit\Framework\TestCase;

class VideoSourceTest extends TestCase
{
    public function test_parses_youtube_watch_and_short_links(): void
    {
        $watch = VideoSource::parse('https://www.youtube.com/watch?v=jfKfPfyJRdk');
        $this->assertNotNull($watch);
        $this->assertSame('youtube', $watch['provider']);
        $this->assertSame('jfKfPfyJRdk', $watch['id']);

        $short = VideoSource::parse('https://youtu.be/jfKfPfyJRdk');
        $this->assertNotNull($short);
        $this->assertSame('jfKfPfyJRdk', $short['id']);
    }

    public function test_parses_vimeo_and_direct_file(): void
    {
        $vimeo = VideoSource::parse('https://vimeo.com/148751763');
        $this->assertNotNull($vimeo);
        $this->assertSame('vimeo', $vimeo['provider']);
        $this->assertSame('148751763', $vimeo['id']);

        $file = VideoSource::parse('https://cdn.example.com/loop.mp4');
        $this->assertNotNull($file);
        $this->assertSame('file', $file['provider']);
        $this->assertSame('https://cdn.example.com/loop.mp4', $file['url']);
    }

    public function test_rejects_non_video_urls(): void
    {
        $this->assertNull(VideoSource::parse('https://example.com/photo.jpg'));
        $this->assertNull(VideoSource::parse('javascript:alert(1)'));
        $this->assertNull(VideoSource::parse('not a url'));
    }
}
