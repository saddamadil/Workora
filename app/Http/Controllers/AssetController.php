<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\ImageStore;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves logos and profile photos. They live on the private disk, so nothing is reachable
 * by a guessable URL; each route checks that the viewer is allowed to see that image.
 */
class AssetController extends Controller
{
    public function __construct(private ImageStore $images, private Tenancy $tenancy) {}

    /** A person's photo: themselves, or someone they share a company with. */
    public function avatar(Request $request, User $user): StreamedResponse
    {
        $me = $request->user();

        $related = $me->id === $user->id
            || OrganizationMember::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'active')
                ->whereIn('organization_id', OrganizationMember::withoutGlobalScopes()->where('user_id', $me->id)->where('status', 'active')->select('organization_id'))
                ->exists();

        abort_unless($related, 404);

        return $this->send($user->avatar_path);
    }

    public function companyLogo(): StreamedResponse
    {
        abort_unless($this->tenancy->check(), 404);

        return $this->send($this->tenancy->organization()->logo_path);
    }

    /** Your own signature, for the profile form's preview. Never served to anyone else. */
    public function signature(Request $request): StreamedResponse
    {
        $profile = \App\Models\FreelancerProfile::firstWhere('user_id', $request->user()->id);

        return $this->send($profile?->signature_path);
    }

    public function clientLogo(Client $client): StreamedResponse
    {
        abort_unless($this->tenancy->isStaff(), 404);

        return $this->send($client->logo_path);
    }

    private function send(?string $path): StreamedResponse
    {
        abort_unless($this->images->exists($path), 404);

        return Storage::disk(config('workora.disk'))->response($path, null, [
            'Content-Type' => $this->images->mime($path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
