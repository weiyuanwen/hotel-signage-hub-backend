<?php

namespace App\Domains\Content;

class TemplateGallery
{
    /** @return array<string, array{url: string, mood: string}> */
    public static function all(): array
    {
        return [
            'sunlit' => [
                'url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'linen',
            ],
            'pool' => [
                'url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'dusk',
            ],
            'cafe' => [
                'url' => 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'linen',
            ],
            'garden' => [
                'url' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'garden',
            ],
            'coastal' => [
                'url' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'harbor',
            ],
            'lobby' => [
                'url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'stone',
            ],
            'terrace' => [
                'url' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'dusk',
            ],
            'spa' => [
                'url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1920&q=80',
                'mood' => 'stone',
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
            default => 'terrace',
        };
    }
}
