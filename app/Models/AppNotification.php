<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An in-app notification for one person. (Laravel's own `notifications` table is unused.) */
class AppNotification extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public $timestamps = false;

    protected $table = 'app_notifications';
}
