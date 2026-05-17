<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = $user->is_super_admin
            ? Organization::query()->orderBy('name')
            : $user->organizations()->orderBy('name');

        $orgs = $query->get()->map(fn (Organization $o) => [
            'id' => $o->id,
            'name' => $o->name,
            'slug' => $o->slug,
            'role' => $this->resolvedRole($user, $o),
            'logo_url' => $o->logoPublicUrl(),
        ]);

        return Inertia::render('Organizations/Index', [
            'organizations' => $orgs,
            'canCreate' => $user->is_super_admin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_super_admin, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $slug = Str::slug($validated['name']).'-'.Str::lower(Str::random(8));
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['name']).'-'.Str::lower(Str::random(8));
        }

        $org = Organization::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $org->users()->attach($request->user()->id, [
            'role' => OrganizationRole::Admin->value,
        ]);

        return redirect()->route('organizations.show', $org);
    }

    public function show(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $organization->loadCount(['members']);

        $events = $organization->events()
            ->with('eventType')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'event_date' => $e->event_date->format('Y-m-d'),
                'event_type_name' => $e->eventType->name,
                'finalized' => $e->finalized_at !== null,
                'rsvp_counts' => $e->rsvpCounts(),
            ]);

        return Inertia::render('Organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $this->resolvedRole($request->user(), $organization),
                'logo_url' => $organization->logoPublicUrl(),
                'brand_primary' => $organization->brand_primary,
                'brand_accent' => $organization->brand_accent,
                'counts' => [
                    'members' => $organization->members_count,
                ],
            ],
            'events' => $events,
        ]);
    }

    /**
     * Super admins always receive 'admin' so UI gates (nav, settings) work correctly
     * even for orgs they are not explicitly a member of.
     */
    private function resolvedRole(\App\Models\User $user, Organization $organization): ?string
    {
        if ($user->is_super_admin) {
            return OrganizationRole::Admin->value;
        }

        return $organization->roleFor($user)?->value;
    }
}
