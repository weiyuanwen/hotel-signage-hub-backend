<?php

namespace App\Domains\Content;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaStore
{
    public static function disk(): string
    {
        return (string) config('filesystems.media', 'public');
    }

    public function put(UploadedFile $file, string $directory, string $filename): string
    {
        $path = $file->storeAs($directory, $filename, [
            'disk' => self::disk(),
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Không lưu được file lên bộ nhớ.');
        }

        return $path;
    }

    public function createAsset(
        int $hotelId,
        string $type,
        UploadedFile $file,
        string $directory,
        string $filename,
    ): MediaAsset {
        $path = $this->put($file, $directory, $filename);

        return MediaAsset::query()->create([
            'hotel_id' => $hotelId,
            'type' => $type,
            'disk' => self::disk(),
            'path' => $path,
            'mime' => (string) $file->getMimeType(),
            'bytes' => (int) $file->getSize(),
        ]);
    }

    public function delete(MediaAsset $asset): void
    {
        if ($asset->disk === 'external' || $asset->path === '') {
            return;
        }

        Storage::disk($asset->disk)->delete($asset->path);
    }
}
