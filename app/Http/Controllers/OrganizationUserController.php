<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUserController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('manageSettings', $organization);

        $users = $organization->users()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ]);

        return Inertia::render('OrganizationUsers/Index', [
            'organization' => $this->orgProps($request, $organization),
            'users' => $users,
            'roles' => array_map(fn (OrganizationRole $r) => $r->value, OrganizationRole::cases()),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:'.implode(',', array_column(OrganizationRole::cases(), 'value'))],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if (! $user) {
            return redirect()->back()->withErrors(['email' => 'No user with that email. They must register first.']);
        }

        if ($organization->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->withErrors(['email' => 'User is already in this organization.']);
        }

        $organization->users()->attach($user->id, ['role' => $validated['role']]);

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors(['role' => 'You cannot change your own role here.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', array_column(OrganizationRole::cases(), 'value'))],
        ]);

        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        $organization->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors(['user' => 'You cannot remove yourself.']);
        }

        $organization->users()->detach($user->id);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function orgProps(Request $request, Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $organization->roleFor($request->user())?->value,
            'logo_url' => $organization->logoPublicUrl(),
            'brand_primary' => $organization->brand_primary,
            'brand_accent' => $organization->brand_accent,
        ];
    }
}
