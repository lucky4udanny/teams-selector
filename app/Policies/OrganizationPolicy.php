<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user) !== null;
    }

    public function manageSettings(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user) === OrganizationRole::Admin;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        $r = $organization->roleFor($user);

        return $r === OrganizationRole::Admin || $r === OrganizationRole::Organizer;
    }

    public function manageRules(User $user, Organization $organization): bool
    {
        return $this->manageMembers($user, $organization);
    }

    public function manageDrafts(User $user, Organization $organization): bool
    {
        return $this->manageMembers($user, $organization);
    }

    public function approveSelections(User $user, Organization $organization): bool
    {
        $r = $organization->roleFor($user);

        return $r === OrganizationRole::Admin || $r === OrganizationRole::Organizer;
    }
}
