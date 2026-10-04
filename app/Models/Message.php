<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One message in a client conversation: general, or narrowed to a single project. */
class Message extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'project_id',
        'user_id',
        'parent_id',
        'invoice_id',
        'body',
        'is_important',
    ];

    protected function casts(): array
    {
        return ['is_important' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}
