<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deliberately not tenant-scoped: a visitor arrives with only a token and no
 * session, so the link has to be found before the company is known. The token
 * is the credential. Everything reached through the link is loaded afterwards
 * inside Tenancy::forOrganization().
 */
class ShareLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'file_id', 'token', 'password', 'expires_at',
        'max_downloads', 'download_count', 'view_count', 'revoked_at', 'created_by',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public static function generateToken(): string
    {
        return Str::random(40);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class)->withoutGlobalScopes()->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function checkPassword(string $attempt): bool
    {
        return $this->password !== null && Hash::check($attempt, $this->password);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function downloadsExhausted(): bool
    {
        return $this->max_downloads !== null && $this->download_count >= $this->max_downloads;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->downloadsExhausted()
            && $this->file !== null && ! $this->file->trashed();
    }

    /** Why the link no longer works, in words a visitor can act on. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => 'The owner turned this link off.',
            $this->isExpired() => 'This link has expired.',
            $this->downloadsExhausted() => 'This link has reached its download limit.',
            $this->file === null || $this->file->trashed() => 'This file is no longer available.',
            default => null,
        };
    }

    public function url(): string
    {
        return route('share.show', $this->token);
    }
}
