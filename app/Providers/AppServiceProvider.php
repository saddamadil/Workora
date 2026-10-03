<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Invoice;
use App\Models\Project;
use App\Policies\FilePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Support\Permissions;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        // Shared hosts often terminate TLS in front of PHP, so the request can look like
        // plain HTTP. Generate https links (share URLs, OAuth redirect) when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Coarse capabilities, all read from App\Support\Permissions.
        $role = fn () => app(Tenancy::class)->role();
        Gate::define('staff', fn () => $role() !== null && ! $role()->isFreelancer());
        Gate::define('freelancer', fn () => $role()?->isFreelancer() ?? false);
        foreach (['track-time', 'see-money', 'manage-team', 'manage-clients', 'manage-contracts', 'review-time'] as $ability) {
            Gate::define($ability, fn () => Permissions::allows($ability, $role()));
        }
        Gate::define('pay', fn () => Permissions::allows('approve-invoices', $role()));

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);

        // Fail loudly in development when a relationship isn't eager loaded, or
        // when code sets an attribute that isn't fillable.
        // Cheap and needed by nearly every page (no queries): who and where we are.
        View::composer('*', function ($view) {
            $tenancy = app(Tenancy::class);

            if ($tenancy->check()) {
                $view->with(['org' => $tenancy->organization(), 'role' => $tenancy->role()]);
            }
        });

        // The sidebar shows storage used and the company switcher on every signed-in page.
        View::composer('layouts.app', function ($view) {
            $tenancy = app(Tenancy::class);

            if ($tenancy->check()) {
                $user = auth()->user();
                $view->with([
                    'usedBytes' => (int) File::sum('size_bytes'),
                    'totalFiles' => File::count(),
                    'myOrgs' => $user->organizations()->wherePivot('status', 'active')->get(['organizations.id', 'organizations.name', 'organizations.slug']),
                ]);
            }
        });

        Model::shouldBeStrict($this->app->isLocal());
    }
}
