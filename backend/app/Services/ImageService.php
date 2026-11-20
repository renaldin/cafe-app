<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Simpan gambar langsung ke public/uploads (tanpa storage:link),
 * dikompres ke WebP agar halaman pelanggan ringan.
 */
class ImageService
{
    public static function basePath(): string
    {
        return rtrim(env('PUBLIC_UPLOAD_PATH') ?: public_path('uploads'), '/\\');
    }

    public function store(UploadedFile $file, string $folder, int $max = 800, int $quality = 75): string
    {
        $dir = self::basePath().DIRECTORY_SEPARATOR.$folder;
        File::ensureDirectoryExists($dir, 0755);
        $name = Str::lower(Str::random(20)).'.webp';
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());
        $image->scaleDown(width: $max, height: $max);
        $image->toWebp($quality)->save($dir.DIRECTORY_SEPARATOR.$name);

        return 'uploads/'.$folder.'/'.$name;
    }

    public function delete(?string $relative): void
    {
        if (! $relative || ! str_starts_with($relative, 'uploads/')) {
            return;
        }
        $path = self::basePath().DIRECTORY_SEPARATOR.substr($relative, strlen('uploads/'));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function url(?string $relative): ?string
    {
        if (! $relative) {
            return null;
        }
        if (str_starts_with($relative, 'http')) {
            return $relative;
        }
        $base = env('PUBLIC_UPLOAD_URL');
        if ($base && str_starts_with($relative, 'uploads/')) {
            return rtrim($base, '/').'/'.substr($relative, strlen('uploads/'));
        }

        return rtrim(config('app.url'), '/').'/'.$relative;
    }
}
