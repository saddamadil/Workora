<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Policies\InvoicePolicy;
use App\Policies\ProjectPolicy;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request. Singleton is essential — a second
        // instance would mean two different answers to "which company is this".
        $this->app->singleton(Tenancy::class);
    }

    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        // Fail loudly in development when a relationship isn't eager loaded, or
        // when code sets an attribute that isn't fillable.
        Model::shouldBeStrict($this->app->isLocal());
    }
}
