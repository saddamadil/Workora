<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use App\Models\Invoice;
use App\Services\ImageStore;
use App\Support\Money;
use App\Support\TaxFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('staff');
        $org = app(Tenancy::class)->organization();
        $filter = (string) $request->query('filter', 'all');
        $search = trim((string) $request->query('q'));

        $clients = Client::query()
            ->withCount(['projects', 'projects as active_projects_count' => fn ($q) => $q->where('status', 'active')])
            ->when($filter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($filter === 'inactive', fn ($q) => $q->where('status', 'inactive'))
            ->when(in_array($filter, ['company', 'individual'], true), fn ($q) => $q->where('type', $filter))
            ->when($filter === 'domestic', fn ($q) => $q->where(fn ($q) => $q->whereNull('country_code')->orWhere('country_code', $org->country_code)))
            ->when($filter === 'international', fn ($q) => $q->whereNotNull('country_code')->where('country_code', '!=', $org->country_code ?? ''))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('contact_name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')->get();

        // What each client still owes, kept per currency.
        $owed = Gate::allows('see-money') || app(Tenancy::class)->issuesOwnInvoices()
            ? Invoice::query()->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid'])->whereIn('client_id', $clients->pluck('id'))->get(['client_id', 'currency', 'total_minor', 'amount_paid_minor'])
                ->groupBy('client_id')->map(fn ($rows) => $rows->groupBy('currency')->map(fn ($g) => (int) $g->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor))->all())
            : collect();
        $lastActivity = Invoice::query()->whereIn('client_id', $clients->pluck('id'))->selectRaw('client_id, max(updated_at) as last')->groupBy('client_id')->pluck('last', 'client_id');

        return view('clients.index', compact('clients', 'filter', 'search', 'owed', 'lastActivity'));
    }

    public function create(): View
    {
        $this->authorize('manage-clients');

        return view('clients.form', ['client' => new Client(['default_currency' => app(\App\Support\Tenancy::class)->organization()->base_currency])]);
    }

    public function show(Request $request, Client $client): View
    {
        $this->authorize('staff');
        $tab = in_array($request->query('tab'), ['overview', 'projects', 'invoices'], true) ? $request->query('tab') : 'overview';
        $money = Gate::allows('see-money') || app(Tenancy::class)->issuesOwnInvoices();

        $projects = $client->projects()->withCount([
            'tasks as tasks_total' => fn ($q) => $q->where('status', '!=', 'cancelled'),
            'tasks as tasks_done' => fn ($q) => $q->where('status', 'approved'),
        ])->latest()->get();
        $invoices = $money ? Invoice::query()->with('freelancer:id,name')->where('client_id', $client->id)->latest('issue_date')->get() : collect();
        $sent = $invoices->whereNotIn('status', ['draft', 'rejected', 'void', 'refunded']);

        return view('clients.show', [
            'client' => $client,
            'tab' => $tab,
            'projects' => $projects,
            'invoices' => $invoices,
            'billed' => $sent->groupBy('currency')->map(fn ($g) => (int) $g->sum('total_minor'))->all(),
            'outstanding' => $sent->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid'])->groupBy('currency')->map(fn ($g) => (int) $g->sum(fn ($i) => $i->total_minor - $i->amount_paid_minor))->all(),
            'portalUsers' => OrganizationMember::query()->with('user:id,name,email')->where('client_id', $client->id)->whereIn('role', ['client', 'client_member'])->get(),
            'pendingInvite' => Invitation::query()->where('client_id', $client->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->first(),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('manage-clients');

        return view('clients.form', ['client' => $client]);
    }

    public function store(Request $request, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $data = $this->validated($request, $images);

        // The same client twice splits their invoices and history in two, so stop it here.
        $existing = $this->findDuplicate($data['name'], $data['email'] ?? null);
        if ($existing) {
            return back()->withInput()->with('duplicate', ['id' => $existing->id, 'name' => $existing->name]);
        }

        $client = Client::create($data);
        AuditLog::record('client.created', $client);

        return redirect()->route('clients.show', $client)->with('status', 'Client added. You can now invite them to their portal.');
    }

    public function update(Request $request, Client $client, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $data = $this->validated($request, $images, $client);
        $existing = $this->findDuplicate($data['name'], $data['email'] ?? null, $client->id);
        if ($existing) {
            return back()->withInput()->with('duplicate', ['id' => $existing->id, 'name' => $existing->name]);
        }
        $client->update($data);

        return redirect()->route('clients.show', $client)->with('status', 'Client saved.');
    }

    public function destroy(Client $client, ImageStore $images): RedirectResponse
    {
        $this->authorize('manage-clients');
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client removed. Their projects and past invoices are kept.');
    }

    /** A client in this workspace with the same email, or the same name. */
    private function findDuplicate(string $name, ?string $email, ?string $exceptId = null): ?Client
    {
        return Client::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(fn ($q) => $q->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->when($email, fn ($q) => $q->orWhereRaw('lower(email) = ?', [mb_strtolower($email)])))
            ->first();
    }

    /** Email the client a link to their portal. Connects an existing account when they already have one. */
    public function invite(Request $request, Client $client, Tenancy $tenancy): RedirectResponse
    {
        $this->authorize('manage-clients');
        abort_if(blank($client->email), 422, 'Add the client\'s email first.');

        $email = strtolower($client->email);
        $existing = User::where('email', $email)->first();
        if ($existing && OrganizationMember::query()->where('user_id', $existing->id)->whereNotIn('role', ['client', 'client_member'])->exists()) {
            return back()->with('error', 'That email already belongs to someone on your team, so it cannot also be a client login.');
        }

        $invitation = Invitation::query()->where('client_id', $client->id)->whereRaw('lower(email) = ?', [$email])->whereNull('accepted_at')->where('expires_at', '>', now())->first()
            ?? Invitation::create(['client_id' => $client->id, 'email' => $email, 'role' => 'client', 'member_type' => 'client', 'invited_by' => $request->user()->id]);

        $link = route('invite.show', $invitation->token);
        $who = $request->user()->name;
        try {
            Mail::raw("{$who} invited you to your client portal on Freelancy.\n\nSee projects, share files and view invoices:\n{$link}\n", fn ($m) => $m
                ->to($email)->subject("{$who} invited you to Freelancy"));
        } catch (\Throwable) {
            // Mail may not be set up yet; the link is shown below either way.
        }
        AuditLog::record('client.invited', $client);

        return back()->with('status', 'Invitation ready. If email is not set up, send them this link: '.$link);
    }

    private function validated(Request $request, ImageStore $images, ?Client $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['sometimes', Rule::in(['company', 'individual'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'website' => ['nullable', 'url', 'max:200'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', Rule::in(array_keys(Money::CURRENCIES))],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_ids' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        [$taxIds, $errors] = TaxFields::clean($data['country_code'] ?? null, $data['tax_ids'] ?? []);
        if ($errors) {
            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($m, $k) => ["tax_ids.$k" => $m])->all());
        }

        $data['tax_ids'] = $taxIds ?: null;
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;

        try {
            if ($request->hasFile('logo')) {
                $images->delete($existing?->logo_path);
                $data['logo_path'] = $images->store($request->file('logo'), 'branding/clients', 600);
            }
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }
        unset($data['logo']);

        return $data;
    }
}
