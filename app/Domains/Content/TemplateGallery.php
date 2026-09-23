<?php

namespace App\Domains\Content;

class TemplateGallery
{
    private const FALLBACK_PUBLIC = 'https://pub-5f8fb64110c5413ab7909c445603eef3.r2.dev';

    public static function publicUrl(string $path): string
    {
        $base = rtrim((string) (config('filesystems.disks.r2.url') ?: self::FALLBACK_PUBLIC), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /** @return array<string, array{url: string, mood: string}> */
    public static function all(): array
    {
        return [
            'sunlit' => [
                'url' => self::publicUrl('landing/gallery/sunlit.jpg'),
                'mood' => 'linen',
            ],
            'pool' => [
                'url' => self::publicUrl('landing/gallery/pool.jpg'),
                'mood' => 'dusk',
            ],
            'cafe' => [
                'url' => self::publicUrl('landing/gallery/cafe.jpg'),
                'mood' => 'linen',
            ],
            'garden' => [
                'url' => self::publicUrl('landing/gallery/garden.jpg'),
                'mood' => 'garden',
            ],
            'coastal' => [
                'url' => self::publicUrl('landing/gallery/coastal.jpg'),
                'mood' => 'harbor',
            ],
            'lobby' => [
                'url' => self::publicUrl('landing/gallery/lobby.jpg'),
                'mood' => 'stone',
            ],
            'terrace' => [
                'url' => self::publicUrl('landing/gallery/terrace.jpg'),
                'mood' => 'dusk',
            ],
            'spa' => [
                'url' => self::publicUrl('landing/gallery/spa.jpg'),
                'mood' => 'stone',
            ],
            'suite' => [
                'url' => self::publicUrl('landing/gallery/suite.jpg'),
                'mood' => 'vista',
            ],
        ];
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function url(string $id): ?string
    {
        return self::all()[$id]['url'] ?? null;
    }

    public static function defaultId(string $templateKey): string
    {
        return match ($templateKey) {
            'linen' => 'sunlit',
            'harbor' => 'coastal',
            'garden' => 'garden',
            'stone' => 'lobby',
            'vista' => 'suite',
            default => 'terrace',
        };
    }
}
