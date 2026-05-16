<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->organization->roleFor($user) !== null;
    }

    public function update(User $user, Event $event): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $event->organization);
    }

    public function delete(User $user, Event $event): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $event->organization);
    }

    public function finalize(User $user, Event $event): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $event->organization);
    }

    public function revertFinal(User $user, Event $event): bool
    {
        return $event->organization->roleFor($user) === OrganizationRole::Admin;
    }
}
