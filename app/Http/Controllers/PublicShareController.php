<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use App\Services\FileLibrary;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pages a visitor reaches with nothing but a link. No sign-in, no company in the session. */
class PublicShareController extends Controller
{
    public function __construct(private Tenancy $tenancy, private FileLibrary $library) {}

    public function show(Request $request, string $token): View|Response
    {
        $link = $this->resolve($token);

        if ($reason = $link->unavailableReason()) {
            return response()->view('share.unavailable', ['reason' => $reason], 410);
        }

        if ($link->hasPassword() && ! $this->unlocked($request, $link)) {
            return view('share.locked', ['link' => $link, 'file' => $link->file]);
        }

        $link->increment('view_count');

        return view('share.show', [
            'link' => $link,
            'file' => $link->file,
            'owner' => $link->organization->name,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolve($token);
        $request->validate(['password' => ['required', 'string']]);

        if (! $link->checkPassword($request->input('password'))) {
            return back()->withErrors(['password' => 'That password is not right.']);
        }

        $request->session()->put("share_unlocked.{$link->id}", true);

        return redirect()->route('share.show', $token);
    }

    public function preview(Request $request, string $token): StreamedResponse
    {
        $link = $this->usable($request, $token);

        abort_unless($link->file->isInlineViewable(), 404);

        return $this->library->response($link->file, inline: true);
    }

    public function download(Request $request, string $token): StreamedResponse
    {
        $link = $this->usable($request, $token);

        // Count first, atomically, so two simultaneous visitors cannot both take the last download.
        $claimed = ShareLink::whereKey($link->id)
            ->where(fn ($q) => $q->whereNull('max_downloads')->orWhereColumn('download_count', '<', 'max_downloads'))
            ->increment('download_count');

        abort_if($claimed === 0, 410, 'This link has reached its download limit.');

        return $this->library->response($link->file, inline: false);
    }

    private function resolve(string $token): ShareLink
    {
        $link = ShareLink::with('organization')->where('token', $token)->firstOrFail();

        // The file lives behind tenant scoping (and Postgres RLS), so load it as that company.
        $this->tenancy->forOrganization($link->organization, fn () => $link->load('file'));

        return $link;
    }

    private function usable(Request $request, string $token): ShareLink
    {
        $link = $this->resolve($token);

        abort_if($link->unavailableReason() !== null, 410, $link->unavailableReason());
        abort_if($link->hasPassword() && ! $this->unlocked($request, $link), 403);

        return $link;
    }

    private function unlocked(Request $request, ShareLink $link): bool
    {
        return (bool) $request->session()->get("share_unlocked.{$link->id}", false);
    }
}
