<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case TeamMember = 'team_member';
    case Finance = 'finance';
    case Viewer = 'viewer';
    case Freelancer = 'freelancer';

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
        };
    }

    /** Roles that can see every project in the company without being a project member. */
    public function seesAllProjects(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance, self::Viewer], true);
    }

    /** Roles that can see budgets, rates, invoices and payments. */
    public function seesMoney(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }

    /** Roles that can approve submitted work. */
    public function canApproveWork(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::ProjectManager], true);
    }

    /** Roles that can approve invoices and release payments. */
    public function canApprovePayment(): bool
    {
        return in_array($this, [self::Owner, self::Finance], true);
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
