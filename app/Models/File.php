<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'attachable_type', 'attachable_id',
        'folder', 'original_name', 'path', 'disk', 'mime_type', 'size_bytes',
        'checksum', 'version', 'replaces_file_id', 'visibility', 'uploaded_by', 'client_id', 'visible_to_client',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'version' => 'integer', 'visible_to_client' => 'boolean'];
    }

    /** Freelancers see their own uploads and files on projects they belong to. */
    public function scopeVisibleTo($query, User $user)
    {
        if (! app(Tenancy::class)->isFreelancer()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('uploaded_by', $user->id)
            ->orWhereIn('project_id', ProjectMember::query()->where('user_id', $user->id)->select('project_id')));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_file_id');
    }

    public function replacedBy(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_file_id');
    }

    /**
     * A short-lived signed URL. Files live on a private disk, so nothing is
     * served by guessable path — access always goes through a policy check.
     */
    public function temporaryUrl(int $minutes = 10): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Safe to render in the browser. SVG is excluded on purpose: it can carry
     * script, so it is only ever served as a download.
     */
    public function isInlineViewable(): bool
    {
        $mime = (string) $this->mime_type;

        return $mime === 'application/pdf'
            || (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /** Bootstrap Icons class and a tint for the file-type badge. */
    public function icon(): array
    {
        $ext = $this->extension();

        return match (true) {
            $this->isImage() => ['bi-file-earmark-image', 'text-emerald-600 bg-emerald-50'],
            $ext === 'pdf' => ['bi-file-earmark-pdf', 'text-red-600 bg-red-50'],
            in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt', 'md']) => ['bi-file-earmark-text', 'text-blue-600 bg-blue-50'],
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods']) => ['bi-file-earmark-spreadsheet', 'text-green-600 bg-green-50'],
            in_array($ext, ['ppt', 'pptx', 'odp', 'key']) => ['bi-file-earmark-slides', 'text-orange-600 bg-orange-50'],
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => ['bi-file-earmark-zip', 'text-amber-600 bg-amber-50'],
            str_starts_with((string) $this->mime_type, 'video/') => ['bi-file-earmark-play', 'text-purple-600 bg-purple-50'],
            str_starts_with((string) $this->mime_type, 'audio/') => ['bi-file-earmark-music', 'text-pink-600 bg-pink-50'],
            default => ['bi-file-earmark', 'text-slate-600 bg-slate-100'],
        };
    }

    public function humanSize(): string
    {
        return static::formatBytes($this->size_bytes);
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1).' '.$units[$unit];
    }
}
