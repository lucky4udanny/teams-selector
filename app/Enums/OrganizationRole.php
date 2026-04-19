<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Viewer = 'viewer';

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Admin || $this === self::Organizer;
    }

    public function canManageRules(): bool
    {
        return $this === self::Admin || $this === self::Organizer;
    }

    public function canApproveSelections(): bool
    {
        return $this === self::Admin || $this === self::Organizer;
    }
}
