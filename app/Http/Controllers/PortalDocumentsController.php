<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Proposal;
use App\Services\ClientContext;
use App\Services\Notifier;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** What a client reads and signs: quotes and agreements. Only the client owner can accept or sign; colleagues can read. */
class PortalDocumentsController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx, private Notifier $notifier) {}

    private function client(): Client
    {
        $id = $this->tenancy->clientId();
        abort_if($id === null, 403);

        return Client::query()->findOrFail($id);
    }

    private function mayAct(): void
    {
        abort_unless($this->tenancy->isClientOwner(), 403, 'Only the account owner can accept or sign. Ask them to do it.');
    }

    // ------------------------------------------------------------------ quotes

    public function quotes(): View
    {
        $client = $this->client();

        return view('portal.quotes', ['quotes' => Proposal::query()->where('client_id', $client->id)->whereIn('status', ['sent', 'accepted', 'declined'])->latest()->get()]);
    }

    public function quote(Proposal $quote): View
    {
        $this->owned($quote);

        return view('portal.quote', ['quote' => $quote->load('project:id,name,slug')]);
    }

    public function acceptQuote(Request $request, Proposal $quote): RedirectResponse
    {
        $this->owned($quote);
        $this->mayAct();
        abort_unless($quote->canRespond(), 422, 'This quote can no longer be accepted.');
        $data = $request->validate(['signed_name' => ['required', 'string', 'max:120'], 'agree' => ['accepted']]);

        $project = QuoteController::startProject($quote, $request->user()->id);
        $quote->update(['status' => 'accepted', 'responded_at' => now(), 'signed_name' => $data['signed_name'], 'signed_ip' => $request->ip(), 'project_id' => $project->id]);
        AuditLog::record('quote.accepted', $quote, ['client_id' => $quote->client_id, 'project_id' => $project->id]);
        $this->notifier->send($this->ctx->staffToNotify()->push(\App\Models\User::query()->find($quote->user_id))->filter()->unique('id'), 'approval', $this->client()->name.' accepted '.$quote->number, $quote->title.' - a project was created.', route('projects.show', $project), $request->user());

        return redirect()->route('portal.quotes.show', $quote)->with('status', 'Quote accepted. Your freelancer has been told and the project is set up.');
    }

    public function declineQuote(Request $request, Proposal $quote): RedirectResponse
    {
        $this->owned($quote);
        $this->mayAct();
        abort_unless($quote->canRespond(), 422);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);
        $quote->update(['status' => 'declined', 'responded_at' => now(), 'decline_reason' => $data['reason'] ?? null]);
        AuditLog::record('quote.declined', $quote, ['client_id' => $quote->client_id]);
        $this->notifier->send(\App\Models\User::query()->find($quote->user_id), 'approval', $this->client()->name.' declined '.$quote->number, $data['reason'] ?? null, route('quotes.show', $quote), $request->user());

        return back()->with('status', 'Quote declined.');
    }

    private function owned(Proposal $quote): void
    {
        abort_unless($quote->client_id === $this->client()->id && in_array($quote->status, ['sent', 'accepted', 'declined'], true), 404);
    }

    // ------------------------------------------------------------------ agreements

    public function agreements(): View
    {
        $client = $this->client();

        return view('portal.agreements', ['agreements' => Agreement::query()->where('client_id', $client->id)->whereIn('status', ['sent', 'signed', 'declined'])->latest()->get()]);
    }

    public function agreement(Agreement $agreement): View
    {
        $this->ownedAgreement($agreement);

        return view('portal.agreement', ['agreement' => $agreement]);
    }

    public function sign(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->ownedAgreement($agreement);
        $this->mayAct();
        abort_unless($agreement->status === 'sent', 422, 'This agreement is not waiting for a signature.');
        abort_unless($agreement->intact(), 422, 'The text changed after it was sent. Ask for a fresh copy.');
        $data = $request->validate(['signed_name' => ['required', 'string', 'max:120'], 'agree' => ['accepted']]);

        $agreement->update(['status' => 'signed', 'signed_at' => now(), 'signed_name' => $data['signed_name'], 'signed_ip' => $request->ip(), 'signed_by' => $request->user()->id]);
        AuditLog::record('agreement.signed', $agreement, ['client_id' => $agreement->client_id, 'project_id' => $agreement->project_id]);
        $this->notifier->send(\App\Models\User::query()->find($agreement->user_id), 'approval', $this->client()->name.' signed '.$agreement->title, 'Signed by '.$data['signed_name'], route('agreements.show', $agreement), $request->user());

        return redirect()->route('portal.agreements.show', $agreement)->with('status', 'Signed. A copy is kept with the agreement.');
    }

    public function declineAgreement(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->ownedAgreement($agreement);
        $this->mayAct();
        abort_unless($agreement->status === 'sent', 422);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);
        $agreement->update(['status' => 'declined', 'decline_reason' => $data['reason'] ?? null]);
        AuditLog::record('agreement.declined', $agreement, ['client_id' => $agreement->client_id]);
        $this->notifier->send(\App\Models\User::query()->find($agreement->user_id), 'approval', $this->client()->name.' declined '.$agreement->title, $data['reason'] ?? null, route('agreements.show', $agreement), $request->user());

        return back()->with('status', 'Declined. Your freelancer has been told.');
    }

    private function ownedAgreement(Agreement $agreement): void
    {
        abort_unless($agreement->client_id === $this->client()->id && in_array($agreement->status, ['sent', 'signed', 'declined'], true), 404);
    }
}
