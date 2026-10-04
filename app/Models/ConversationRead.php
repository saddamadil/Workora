<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ConversationRead extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'client_id',
        'scope',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public $timestamps = false;
}
