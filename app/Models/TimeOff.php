<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeOff extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $table = 'time_off';

    public const KINDS = ['vacation' => 'Vacation', 'sick' => 'Sick leave', 'holiday' => 'Public holiday', 'other' => 'Other'];

    protected $fillable = ['organization_id', 'user_id', 'starts_on', 'ends_on', 'kind', 'note'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
