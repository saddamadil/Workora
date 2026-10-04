<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'from_currency', 'to_currency', 'rate', 'effective_on', 'note'];

    protected function casts(): array
    {
        return ['rate' => 'float', 'effective_on' => 'date'];
    }
}
