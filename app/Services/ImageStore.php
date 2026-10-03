<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Logos, profile photos and signatures. An upload is never stored as sent: it is decoded,
 * shrunk and written out again as a fresh PNG or JPEG. That strips metadata (including GPS
 * in phone photos) and anything hidden in the file that is not pixels. SVG is refused,
 * because it can carry script.
 */
class ImageStore
{
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function store(UploadedFile $file, string $folder, int $maxSide = 800): string
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('Use a JPG, PNG or WebP image.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw new RuntimeException('That image could not be read.');
        }

        $source = $this->orient($source, $file->getRealPath(), $mime);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        // Keep transparency (logos, signatures) and white out nothing.
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        $asJpeg = $mime === 'image/jpeg';
        ob_start();
        $asJpeg ? imagejpeg($this->flatten($target), null, 86) : imagepng($target, null, 6);
        $bytes = (string) ob_get_clean();

        $path = trim($folder, '/').'/'.Str::uuid().($asJpeg ? '.jpg' : '.png');
        Storage::disk(config('workora.disk'))->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(config('workora.disk'))->exists($path)) {
            Storage::disk(config('workora.disk'))->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return (bool) $path && Storage::disk(config('workora.disk'))->exists($path);
    }

    public function contents(?string $path): ?string
    {
        return $this->exists($path) ? Storage::disk(config('workora.disk'))->get($path) : null;
    }

    public function mime(?string $path): string
    {
        return str_ends_with((string) $path, '.jpg') ? 'image/jpeg' : 'image/png';
    }

    /** The image as a data: URI, which PDF rendering and printing can use without a network. */
    public function dataUri(?string $path): ?string
    {
        $bytes = $this->contents($path);

        return $bytes === null ? null : 'data:'.$this->mime($path).';base64,'.base64_encode($bytes);
    }

    /** Phone photos carry a rotation flag instead of rotated pixels. */
    private function orient(\GdImage $image, string $file, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($file)['Orientation'] ?? 1;
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }

    /** JPEG has no transparency: put the picture on white. */
    private function flatten(\GdImage $image): \GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }
}
