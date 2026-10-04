<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $tenancy = app(\App\Support\Tenancy::class);

        if ($tenancy->isClient()) {
            return redirect()->route('portal.dashboard');
        }

        if ($tenancy->isSolo() && $tenancy->role() === \App\Enums\OrganizationRole::Owner) {
            return view('dashboard-solo', app(DashboardStats::class)->solo($request->user()));
        }

        return view('dashboard', app(DashboardStats::class)->for($request->user()));
    }
}
