<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
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
            // Empty array keeps older cached JS chunks from crashing on pendingInvitations.length
            'pendingInvitations' => [],
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:'.implode(',', array_column(OrganizationRole::cases(), 'value'))],
        ]);

        $email = strtolower($validated['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user && $organization->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->withErrors([
                'email' => 'This person is already a member of the organization.',
            ]);
        }

        if ($user) {
            $user->update([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $validated['password'],
                'email_verified_at' => now(),
            ]);
        }

        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            $organization->users()->attach($user->id, ['role' => $validated['role']]);
        }

        return redirect()->back()->with('status', 'User '.$user->name.' was added.');
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

    public function updatePassword(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors([
                'password' => 'Change your own password from your profile settings.',
            ]);
        }

        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update(['password' => $validated['password']]);

        return redirect()->back()->with('status', 'Password updated for '.$user->name.'.');
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
        $user = $request->user();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $user->is_super_admin
                ? OrganizationRole::Admin->value
                : $organization->roleFor($user)?->value,
            'logo_url' => $organization->logoPublicUrl(),
            'brand_primary' => $organization->brand_primary,
            'brand_accent' => $organization->brand_accent,
        ];
    }
}
