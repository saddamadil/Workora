<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Scopes\OrganizationScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Put this on every model with an organization_id column.
 *
 * It does two things: filters every query to the current tenant, and stamps
 * organization_id on create so no controller has to remember to.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $model->organization_id = app(Tenancy::class)->idOrFail();
            }
        });

        // A row must never be moved between tenants by an ordinary update.
        static::updating(function ($model) {
            if ($model->isDirty('organization_id') && ! app(Tenancy::class)->isUnscoped()) {
                throw new \RuntimeException(
                    static::class . ' organization_id cannot be changed.'
                );
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
