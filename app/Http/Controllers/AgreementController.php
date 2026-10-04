<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Services\ClientContext;
use App\Services\InvoicePdf;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Agreements a client signs by typing their name. */
class AgreementController extends Controller
{
    public const TEMPLATES = [
        'Services agreement' => "This agreement is between the Service Provider and the Client.\n\n1. Scope of work\nThe Service Provider will deliver the work described in the project brief.\n\n2. Payment\nThe Client will pay invoices within the agreed payment terms.\n\n3. Revisions\nA reasonable number of revisions is included in the agreed price.\n\n4. Ownership\nOwnership of the final deliverables passes to the Client once they are paid in full.\n\n5. Confidentiality\nBoth parties will keep the other's confidential information private.\n\n6. Ending the agreement\nEither party may end this agreement with written notice. Work completed up to that date remains payable.",
        'Non-disclosure agreement' => "Both parties agree to keep confidential any non-public information shared with them during the work, to use it only for the purposes of the work, and not to share it with anyone else without written permission. This obligation continues after the work ends.",
        'Scope change' => "The parties agree to change the scope of the existing work as follows:\n\n(describe the change, the cost and the effect on the timeline)\n\nAll other terms stay the same.",
    ];

    public function __construct(private Tenancy $tenancy) {}

    private function guard(): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->canApprovePayment() || $this->tenancy->role()?->seesMoney(), 403);
    }

    public function index(): View
    {
        $this->guard();

        return view('agreements.index', ['agreements' => Agreement::query()->with('client:id,name')->latest()->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard();

        return view('agreements.form', ['agreement' => new Agreement(['client_id' => $request->query('client'), 'body' => '']), 'clients' => Client::query()->orderBy('name')->get(['id', 'name']), 'projects' => Project::query()->whereNotNull('client_id')->orderBy('name')->get(['id', 'name', 'client_id']), 'templates' => self::TEMPLATES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard();
        $agreement = Agreement::create($this->validated($request) + ['user_id' => $request->user()->id, 'status' => 'draft']);
        AuditLog::record('agreement.created', $agreement, ['client_id' => $agreement->client_id]);

        return redirect()->route('agreements.show', $agreement)->with('status', 'Agreement saved as a draft.');
    }

    public function show(Agreement $agreement): View
    {
        $this->guard();

        return view('agreements.show', ['agreement' => $agreement->load('client:id,name', 'project:id,name,slug', 'author:id,name')]);
    }

    public function edit(Agreement $agreement): View
    {
        $this->guard();
        abort_unless($agreement->status === 'draft', 422, 'Only drafts can be edited. Void it and make a new one.');

        return view('agreements.form', ['agreement' => $agreement, 'clients' => Client::query()->orderBy('name')->get(['id', 'name']), 'projects' => Project::query()->whereNotNull('client_id')->orderBy('name')->get(['id', 'name', 'client_id']), 'templates' => self::TEMPLATES]);
    }

    public function update(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->guard();
        abort_unless($agreement->status === 'draft', 422);
        $agreement->update($this->validated($request));

        return redirect()->route('agreements.show', $agreement)->with('status', 'Saved.');
    }

    public function send(Request $request, Agreement $agreement, ClientContext $ctx, Notifier $notifier): RedirectResponse
    {
        $this->guard();
        abort_unless(in_array($agreement->status, ['draft', 'sent'], true), 422);
        $agreement->update(['status' => 'sent', 'sent_at' => now(), 'body_hash' => $agreement->fingerprint()]);
        AuditLog::record('agreement.sent', $agreement, ['client_id' => $agreement->client_id]);
        $client = Client::query()->findOrFail($agreement->client_id);
        $notifier->send($ctx->clientUsers($client), 'approval', 'Please sign: '.$agreement->title, 'Open it in your portal to read and sign.', route('portal.agreements.show', $agreement), $request->user());

        return back()->with('status', 'Sent. The text is now locked: edits are not possible after sending.');
    }

    public function void(Agreement $agreement): RedirectResponse
    {
        $this->guard();
        abort_if($agreement->status === 'signed', 422, 'A signed agreement cannot be voided. Make a new one that replaces it.');
        $agreement->update(['status' => 'void']);
        AuditLog::record('agreement.void', $agreement, ['client_id' => $agreement->client_id]);

        return back()->with('status', 'Agreement voided.');
    }

    public function destroy(Agreement $agreement): RedirectResponse
    {
        $this->guard();
        abort_unless(in_array($agreement->status, ['draft', 'void', 'declined'], true), 422);
        $agreement->delete();

        return redirect()->route('agreements.index')->with('status', 'Agreement deleted.');
    }

    /** PDF of the agreement with its signature record. Staff, and the client it belongs to (via portal.agreements.pdf). */
    public function pdf(Agreement $agreement, InvoicePdf $pdf)
    {
        $isClient = $this->tenancy->role()?->isClient() ?? false;
        if ($isClient) {
            abort_unless($agreement->client_id === $this->tenancy->clientId() && in_array($agreement->status, ['sent', 'signed', 'declined'], true), 404);
        } else {
            $this->guard();
        }
        $html = view('agreements.print', ['agreement' => $agreement->load('client:id,name', 'author:id,name')])->render();

        return response($pdf->fromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="agreement-'.str($agreement->title)->slug().'.pdf"']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'uuid', Rule::exists('clients', 'id')->where('organization_id', $this->tenancy->id())],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('organization_id', $this->tenancy->id())],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:60000'],
        ]);
    }
}
