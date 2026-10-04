<?php

namespace App\Enums;

use App\Support\Permissions;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case TeamMember = 'team_member';
    case Finance = 'finance';
    case Viewer = 'viewer';
    case Freelancer = 'freelancer';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::ProjectManager => 'Project Manager',
            self::TeamMember => 'Team Member',
            self::Finance => 'Finance',
            self::Viewer => 'Viewer',
            self::Freelancer => 'Freelancer',
            self::Client => 'Client',
        };
    }

    /** Roles that can see every project in the company without being a project member. */
    public function seesAllProjects(): bool
    {
        return Permissions::allows('view-all-projects', $this);
    }

    /** Roles that can see budgets, rates, invoices and payments. */
    public function seesMoney(): bool
    {
        return Permissions::allows('see-money', $this);
    }

    /** Roles that can approve submitted work. */
    public function canApproveWork(): bool
    {
        return Permissions::allows('approve-work', $this);
    }

    /** Roles that can approve invoices and release payments. */
    public function canApprovePayment(): bool
    {
        return Permissions::allows('approve-invoices', $this);
    }

    /** A client's login to the portal. Sees only their own client's work. */
    public function isClient(): bool
    {
        return $this === self::Client;
    }

    public function isFreelancer(): bool
    {
        return $this === self::Freelancer;
    }

    /** Roles a company can assign to its own staff. */
    public static function staffRoles(): array
    {
        return [self::Owner, self::Admin, self::ProjectManager, self::TeamMember, self::Finance, self::Viewer];
    }
}
