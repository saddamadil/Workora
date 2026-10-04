<?php

use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs on every authenticated web request. Everything downstream —
        // the global scope, the RLS session variable, the policies — depends
        // on this having resolved the current company first.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            SetCurrentOrganization::class,
            \App\Http\Middleware\TranslatePhrases::class,
        ]);

        // Route-model binding (/files/{file}) queries tenant-scoped models, so the
        // tenant has to be resolved first. Without this the binding runs against
        // "no company" and every tenant model 404s.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentOrganization::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
