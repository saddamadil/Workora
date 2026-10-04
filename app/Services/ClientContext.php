<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Works out which client a request is about, and who should hear about it. */
class ClientContext
{
    public function __construct(private Tenancy $tenancy) {}

    public function isPortal(): bool
    {
        return $this->tenancy->isClient();
    }

    /** The client record: a client login's own, or the one a staff member picked with ?client= / client_id. */
    public function client(Request $request, bool $required = true): ?Client
    {
        if ($this->isPortal()) {
            return Client::query()->findOrFail($this->tenancy->clientId());
        }

        $id = $request->input('client', $request->input('client_id'));

        if (! $id) {
            abort_if($required, 404);

            return null;
        }

        return Client::query()->findOrFail($id);
    }

    /** People who log in as this client. */
    public function clientUsers(Client $client): Collection
    {
        $ids = OrganizationMember::query()->where('client_id', $client->id)->where('role', OrganizationRole::Client->value)->where('status', 'active')->pluck('user_id');

        return User::query()->whereIn('id', $ids)->get();
    }

    /** Owners and admins of the workspace: who a client's activity is reported to. */
    public function staffToNotify(): Collection
    {
        $ids = OrganizationMember::query()->whereIn('role', ['owner', 'admin'])->where('status', 'active')->pluck('user_id');

        return User::query()->whereIn('id', $ids)->get();
    }

    /** The other side of a conversation: the client's people when staff write, the staff when a client writes. */
    public function audienceFor(User $sender, Client $client): Collection
    {
        return $this->isPortal() ? $this->staffToNotify() : $this->clientUsers($client);
    }
}
