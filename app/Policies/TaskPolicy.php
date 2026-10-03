<?php

namespace App\Policies;

use App\Enums\OrganizationRole as Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Tenancy;

/**
 * Freelancers see only the tasks assigned to them. Staff see tasks in projects
 * they can access. Working on a task (start, submit, log time) needs an
 * assignment; reviewing needs the approve-work role.
 */
class TaskPolicy
{
    public function __construct(private Tenancy $tenancy) {}

    public function view(User $user, Task $task): bool
    {
        $role = $this->tenancy->role();

        return match (true) {
            $role === null => false,
            $role->isFreelancer() => $this->assigned($user, $task),
            $role->seesAllProjects() => true,
            default => $task->project->hasMember($user),
        };
    }

    public function create(User $user): bool
    {
        return Permissions::allows('create-task', $this->tenancy->role());
    }

    public function update(User $user, Task $task): bool
    {
        return $this->create($user) && $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return Permissions::allows('delete-task', $this->tenancy->role())
            && $this->view($user, $task);
    }

    public function work(User $user, Task $task): bool
    {
        return $this->tenancy->role() !== Role::Viewer && $this->assigned($user, $task);
    }

    public function review(User $user, Task $task): bool
    {
        return ($this->tenancy->role()?->canApproveWork() ?? false) && $this->view($user, $task);
    }

    private function assigned(User $user, Task $task): bool
    {
        return $task->assignees()->where('users.id', $user->id)->exists();
    }
}
