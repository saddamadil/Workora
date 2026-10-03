<?php

namespace App\Support;

use App\Enums\OrganizationRole as Role;

/**
 * Who may do what, in one place. The gates, the policies and the "Roles & Permissions"
 * screen all read this list, so the screen cannot say something the app does not enforce.
 */
class Permissions
{
    /** Groups of abilities as shown on the Roles & Permissions screen. */
    public const ABILITIES = [
        'Projects and tasks' => [
            'view-all-projects' => ['See every project, not just their own', [Role::Owner, Role::Admin, Role::Finance, Role::Viewer]],
            'create-project' => ['Create projects', [Role::Owner, Role::Admin, Role::ProjectManager]],
            'delete-project' => ['Archive projects', [Role::Owner, Role::Admin]],
            'create-task' => ['Create and assign tasks', [Role::Owner, Role::Admin, Role::ProjectManager, Role::TeamMember]],
            'delete-task' => ['Delete tasks', [Role::Owner, Role::Admin, Role::ProjectManager]],
            'approve-work' => ['Approve submitted work or send it back', [Role::Owner, Role::Admin, Role::ProjectManager]],
            'manage-clients' => ['Add and edit clients', [Role::Owner, Role::Admin, Role::ProjectManager]],
        ],
        'Time' => [
            'track-time' => ['Track time and submit timesheets', [Role::Owner, Role::Admin, Role::ProjectManager, Role::TeamMember, Role::Finance, Role::Freelancer]],
            'review-time' => ['Approve timesheets', [Role::Owner, Role::Admin, Role::ProjectManager]],
        ],
        'Money' => [
            'see-money' => ['See budgets, rates, contracts and invoices', [Role::Owner, Role::Admin, Role::Finance]],
            'manage-contracts' => ['Create and end contracts', [Role::Owner, Role::Admin, Role::Finance]],
            'approve-invoices' => ['Approve invoices and record payments', [Role::Owner, Role::Finance]],
            'invoice' => ['Issue their own invoices', [Role::Freelancer]],
        ],
        'Team' => [
            'manage-team' => ['Invite people, change roles, edit the company profile', [Role::Owner, Role::Admin]],
        ],
    ];

    public static function allows(string $ability, ?Role $role): bool
    {
        if ($role === null) {
            return false;
        }

        foreach (self::ABILITIES as $group) {
            if (isset($group[$ability])) {
                return in_array($role, $group[$ability][1], true);
            }
        }

        return false;
    }

    /** @return array<string, array<string, array{label: string, roles: array<string, bool>}>> */
    public static function matrix(): array
    {
        $out = [];
        foreach (self::ABILITIES as $group => $abilities) {
            foreach ($abilities as $key => [$label, $roles]) {
                $out[$group][$key] = [
                    'label' => $label,
                    'roles' => collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => in_array($r, $roles, true)])->all(),
                ];
            }
        }

        return $out;
    }
}
