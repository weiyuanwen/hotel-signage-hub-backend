<?php

namespace App\Domains\Content;

final class VideoSource
{
    /**
     * @return array{provider: string, id: ?string, url: string, mime: string}|null
     */
    public static function parse(string $raw): ?array
    {
        $url = trim($raw);
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($id = self::youtubeId($host, $path, $url)) {
            return [
                'provider' => 'youtube',
                'id' => $id,
                'url' => $url,
                'mime' => 'video/youtube',
            ];
        }

        if ($id = self::vimeoId($host, $path)) {
            return [
                'provider' => 'vimeo',
                'id' => $id,
                'url' => $url,
                'mime' => 'video/vimeo',
            ];
        }

        if (preg_match('/\.(mp4|webm|ogg|mov)(\?|$)/i', $path) === 1) {
            return [
                'provider' => 'file',
                'id' => null,
                'url' => $url,
                'mime' => 'video/mp4',
            ];
        }

        return null;
    }

    private static function youtubeId(string $host, string $path, string $url): ?string
    {
        $isYoutube = in_array($host, [
            'youtu.be',
            'youtube.com',
            'm.youtube.com',
            'music.youtube.com',
            'youtube-nocookie.com',
        ], true);

        if (! $isYoutube) {
            return null;
        }

        if ($host === 'youtu.be') {
            $id = explode('/', trim($path, '/'))[0] ?? '';

            return self::validYoutubeId($id) ? $id : null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $fromQuery = $query['v'] ?? null;
        if (is_string($fromQuery) && self::validYoutubeId($fromQuery)) {
            return $fromQuery;
        }

        if (preg_match('#/(embed|shorts|live)/([A-Za-z0-9_-]{11})#', $path, $match) === 1) {
            return $match[2];
        }

        return null;
    }

    private static function validYoutubeId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1;
    }

    private static function vimeoId(string $host, string $path): ?string
    {
        if ($host !== 'vimeo.com' && $host !== 'player.vimeo.com') {
            return null;
        }

        if (preg_match('#/(\d{6,12})#', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
