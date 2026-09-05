<?php

namespace App\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->isUnscoped()) {
            return;
        }

        // No tenant context means no rows. Failing closed is the point: a missing
        // scope should produce an empty result, never another company's data.
        $builder->where(
            $model->qualifyColumn('organization_id'),
            $tenancy->id() ?? '00000000-0000-0000-0000-000000000000'
        );
    }
}
