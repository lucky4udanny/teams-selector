<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Send an invitation email to a new user.
     * Only org admins (and super admins via Gate::before) may call this.
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:'.implode(',', array_column(OrganizationRole::cases(), 'value'))],
        ]);

        $email = strtolower($validated['email']);

        // If the user already belongs to this org, reject
        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser && $organization->users()->where('user_id', $existingUser->id)->exists()) {
            return redirect()->back()->withErrors(['email' => 'This person is already a member of the organization.']);
        }

        // Replace any prior pending invite for this email in this org (preserve accepted records)
        Invitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $request->user()->id,
            'email' => $email,
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['organization', 'invitedBy']);
        Mail::to($email)->send(new InvitationMail($invitation));

        return redirect()->back()->with('status', 'Invitation sent to '.$email.'.');
    }

    /**
     * Show the accept-invitation page.
     * Accessible to guests and authenticated users.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = Invitation::with(['organization', 'invitedBy'])
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('login')
                ->with('error', 'This invitation has expired. Please ask your administrator to send a new one.');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('error', 'This invitation has already been accepted.');
        }

        // If already logged in as the right user, auto-accept and redirect
        $authUser = $request->user();
        if ($authUser && $authUser->email === $invitation->email) {
            $this->attachAndAccept($authUser, $invitation);

            return redirect()->route('organizations.show', $invitation->organization)
                ->with('status', 'Welcome! You have joined '.$invitation->organization->name.'.');
        }

        $userExists = User::query()->where('email', $invitation->email)->exists();

        return Inertia::render('Auth/AcceptInvitation', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization_name' => $invitation->organization->name,
                'invited_by_name' => $invitation->invitedBy->name,
            ],
            'userExists' => $userExists,
        ]);
    }

    /**
     * Accept an invitation: register a new account (or attach an existing one
     * if the user is already logged in) then redirect to the organization.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::with(['organization'])
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('login')
                ->with('error', 'This invitation has expired.');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('error', 'This invitation has already been accepted.');
        }

        // Already logged in — just attach
        $authUser = $request->user();
        if ($authUser) {
            if ($authUser->email !== $invitation->email) {
                return redirect()->back()
                    ->withErrors(['email' => 'You are logged in as a different account. Log out and try again.']);
            }

            $this->attachAndAccept($authUser, $invitation);

            return redirect()->route('organizations.show', $invitation->organization)
                ->with('status', 'You have joined '.$invitation->organization->name.'.');
        }

        // Not logged in — register
        $existingUser = User::query()->where('email', $invitation->email)->first();
        if ($existingUser) {
            return redirect()->back()
                ->withErrors(['email' => 'An account with this email already exists. Please log in to accept this invitation.']);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        Auth::login($user);

        $this->attachAndAccept($user, $invitation);

        return redirect()->route('organizations.show', $invitation->organization)
            ->with('status', 'Welcome to '.$invitation->organization->name.'! Your account has been created.');
    }

    /**
     * Revoke a pending invitation.
     */
    public function destroy(Request $request, Organization $organization, Invitation $invitation): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        if ($invitation->organization_id !== $organization->id) {
            abort(404);
        }

        $invitation->delete();

        return redirect()->back()->with('status', 'Invitation revoked.');
    }

    private function attachAndAccept(User $user, Invitation $invitation): void
    {
        if (! $invitation->organization->users()->where('user_id', $user->id)->exists()) {
            $invitation->organization->users()->attach($user->id, ['role' => $invitation->role]);
        }

        $invitation->update(['accepted_at' => now()]);
    }
}
