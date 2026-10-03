<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Invoice;
use App\Models\Project;
use App\Policies\FilePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProjectPolicy;
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

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(File::class, FilePolicy::class);

        // Fail loudly in development when a relationship isn't eager loaded, or
        // when code sets an attribute that isn't fillable.
        // The sidebar shows storage used on every signed-in page.
        View::composer('layouts.app', function ($view) {
            if (app(Tenancy::class)->check()) {
                $view->with(['usedBytes' => (int) File::sum('size_bytes'), 'totalFiles' => File::count()]);
            }
        });

        Model::shouldBeStrict($this->app->isLocal());
    }
}
