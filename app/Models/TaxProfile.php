<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxProfile extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'name', 'country_code', 'applies_to', 'treatment', 'rate', 'label', 'place_of_supply', 'sac_code', 'lut_reference', 'is_default'];

    protected function casts(): array
    {
        return ['rate' => 'float', 'is_default' => 'boolean'];
    }
}
