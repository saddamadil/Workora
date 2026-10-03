<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ShareLink is not tenant-scoped (visitors reach it by token alone), so every
 * query here filters by the current company by hand.
 */
class ShareLinkController extends Controller
{
    public function index(Tenancy $tenancy): View
    {
        $links = ShareLink::query()
            ->where('organization_id', $tenancy->idOrFail())
            ->with(['file' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()])
            ->latest()
            ->paginate(30);

        return view('shares.index', ['links' => $links]);
    }

    public function destroy(string $link, Tenancy $tenancy): RedirectResponse
    {
        $link = ShareLink::where('organization_id', $tenancy->idOrFail())->findOrFail($link);

        $link->update(['revoked_at' => now()]);

        return back()->with('status', 'Link turned off. Anyone holding it can no longer open the file.');
    }
}
