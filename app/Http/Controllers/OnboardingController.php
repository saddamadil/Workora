<?php

namespace App\Http\Controllers;

use App\Services\Workspaces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        return view('onboarding', ['suggested' => $request->user()->name."'s workspace"]);
    }

    public function store(Request $request, Workspaces $workspaces): RedirectResponse
    {
        $data = $request->validate(['workspace' => ['required', 'string', 'max:120']]);

        $organization = $workspaces->createFor($request->user(), $data['workspace']);
        $request->session()->put('current_organization_id', $organization->id);

        return redirect()->route('dashboard');
    }
}
