<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A document the client signs by typing their name. The text is fingerprinted when it is sent, so edits after signing are detectable. */
class Agreement extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'user_id', 'title', 'body', 'status', 'body_hash', 'sent_at', 'signed_at', 'signed_name', 'signed_ip', 'signed_by', 'decline_reason',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'signed_at' => 'datetime'];
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

    public function fingerprint(): string
    {
        return hash('sha256', $this->title."\n".$this->body);
    }

    /** True while the text still matches what was sent. */
    public function intact(): bool
    {
        return $this->body_hash === null || hash_equals($this->body_hash, $this->fingerprint());
    }
}
