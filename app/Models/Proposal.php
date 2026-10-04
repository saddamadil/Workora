<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A quote the client can accept. Accepting it can start a project. */
class Proposal extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id', 'client_id', 'user_id', 'project_id', 'number', 'title', 'intro', 'terms', 'currency', 'items', 'tax_rate', 'tax_label', 'valid_until',
        'status', 'sent_at', 'responded_at', 'signed_name', 'signed_ip', 'decline_reason',
    ];

    protected function casts(): array
    {
        return ['items' => 'array', 'tax_rate' => 'float', 'valid_until' => 'date', 'sent_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subtotalMinor(): int
    {
        return (int) collect($this->items)->sum(fn ($i) => (int) round(((float) $i['quantity']) * ((int) $i['unit_rate_minor'])));
    }

    public function taxMinor(): int
    {
        return (int) round($this->subtotalMinor() * $this->tax_rate / 100);
    }

    public function totalMinor(): int
    {
        return $this->subtotalMinor() + $this->taxMinor();
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    public function canRespond(): bool
    {
        return $this->status === 'sent' && ! $this->isExpired();
    }
}
