<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Workspaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class WorkoraTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake(config('workora.disk'));
    }

    protected function userWithWorkspace(string $name = 'Alice', string $role = 'owner'): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-pass-1']);
        $org = app(Workspaces::class)->createFor($user, $name.' Co');

        if ($role !== 'owner') {
            $user->memberships()->update(['role' => $role]);
        }

        return $user->refresh();
    }
}
