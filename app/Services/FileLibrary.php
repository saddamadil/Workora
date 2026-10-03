<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The single place a file enters storage, whether it came from a browser upload
 * or an import from Google Drive. Keeping this in one class means checksum,
 * path layout and disk choice cannot drift between the two routes in.
 */
class FileLibrary
{
    public function __construct(private Tenancy $tenancy) {}

    public function storeUpload(UploadedFile $upload, User $user, ?string $folder = null): File
    {
        $name = $this->cleanName($upload->getClientOriginalName());
        $disk = config('workora.disk');
        $path = $this->newPath($name);

        Storage::disk($disk)->putFileAs(dirname($path), $upload, basename($path));

        return File::create([
            'folder' => $folder ?: $this->guessFolder($upload->getMimeType()),
            'original_name' => $name,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $upload->getMimeType() ?: $upload->getClientMimeType(),
            'size_bytes' => $upload->getSize(),
            'checksum' => hash_file('sha256', $upload->getRealPath()),
            'visibility' => 'organization',
            'source' => 'upload',
            'uploaded_by' => $user->id,
        ]);
    }

    /** Store a file that already exists on local disk (a Drive download in a temp file). */
    public function storeFromPath(string $localPath, string $name, ?string $mime, User $user, array $extra = []): File
    {
        $name = $this->cleanName($name);
        $disk = config('workora.disk');
        $path = $this->newPath($name);

        $stream = fopen($localPath, 'rb');
        Storage::disk($disk)->put($path, $stream);
        is_resource($stream) && fclose($stream);

        return File::create(array_merge([
            'folder' => $this->guessFolder($mime),
            'original_name' => $name,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mime,
            'size_bytes' => filesize($localPath),
            'checksum' => hash_file('sha256', $localPath),
            'visibility' => 'organization',
            'source' => 'upload',
            'uploaded_by' => $user->id,
        ], $extra));
    }

    /** Stream a stored file to the browser, inline or as a download. */
    public function response(File $file, bool $inline): StreamedResponse
    {
        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404, 'The file is missing from storage.');

        return $disk->response(
            $file->path,
            $file->original_name,
            array_filter([
                'Content-Type' => $inline ? $file->mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                // Locks down anything rendered inline. PDFs are skipped because
                // the browser's built-in viewer needs to load itself.
                'Content-Security-Policy' => $file->mime_type === 'application/pdf' ? null : "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
                'Cache-Control' => 'private, max-age=300',
            ]),
            $inline ? 'inline' : 'attachment',
        );
    }

    /** Copy a stored file to a local temp path (needed for non-local disks) and return it. */
    public function toTempFile(File $file): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'workora_');
        $read = Storage::disk($file->disk)->readStream($file->path);
        abort_unless($read, 404, 'The file is missing from storage.');

        $write = fopen($tmp, 'wb');
        stream_copy_to_stream($read, $write);
        fclose($write);
        fclose($read);

        return $tmp;
    }

    public function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name, ". \t");

        return $name === '' ? 'untitled' : Str::limit($name, 200, '');
    }

    public function isBlocked(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), config('workora.blocked_extensions'), true);
    }

    private function newPath(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $ext = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.'.$ext : '';

        // Stored under a random name: the original name is metadata, never a path.
        return $this->tenancy->idOrFail().'/'.now()->format('Y/m').'/'.Str::uuid().$ext;
    }

    private function guessFolder(?string $mime): string
    {
        return str_starts_with((string) $mime, 'image/') ? 'Images' : 'Documents';
    }
}
