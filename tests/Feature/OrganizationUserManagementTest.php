<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_new_user_with_password(): void
    {
        $admin = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);

        $response = $this->actingAs($admin)->post(
            route('organizations.users.store', $org),
            [
                'name' => 'New Member',
                'email' => 'new@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => OrganizationRole::Viewer->value,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'User New Member was added.');

        $user = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Password1!', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Viewer->value,
        ]);
    }

    public function test_cannot_add_user_already_in_organization(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);
        $org->users()->attach($member->id, ['role' => OrganizationRole::Viewer->value]);

        $response = $this->actingAs($admin)->from(route('organizations.users.index', $org))
            ->post(route('organizations.users.store', $org), [
                'name' => 'Member',
                'email' => 'member@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => OrganizationRole::Organizer->value,
            ]);

        $response->assertRedirect(route('organizations.users.index', $org));
        $response->assertSessionHasErrors(['email' => 'This person is already a member of the organization.']);
    }

    public function test_existing_user_not_in_org_is_attached_and_password_updated(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);

        $response = $this->actingAs($admin)->post(
            route('organizations.users.store', $org),
            [
                'name' => 'Renamed',
                'email' => 'existing@example.com',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
                'role' => OrganizationRole::Organizer->value,
            ],
        );

        $response->assertRedirect();
        $existing->refresh();
        $this->assertSame('Renamed', $existing->name);
        $this->assertTrue(Hash::check('NewPassword1!', $existing->password));
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $existing->id,
            'role' => OrganizationRole::Organizer->value,
        ]);
    }

    public function test_organizer_cannot_add_users(): void
    {
        $organizer = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($organizer->id, ['role' => OrganizationRole::Organizer->value]);

        $response = $this->actingAs($organizer)->post(
            route('organizations.users.store', $org),
            [
                'name' => 'New Member',
                'email' => 'new@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => OrganizationRole::Viewer->value,
            ],
        );

        $response->assertForbidden();
    }

    public function test_admin_can_reset_member_password(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);
        $org->users()->attach($member->id, ['role' => OrganizationRole::Viewer->value]);

        $response = $this->actingAs($admin)->patch(
            route('organizations.users.password.update', [$org, $member]),
            [
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Password updated for '.$member->name.'.');
        $member->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $member->password));
    }

    public function test_admin_cannot_reset_own_password_via_org_users(): void
    {
        $admin = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);

        $response = $this->actingAs($admin)->from(route('organizations.users.index', $org))
            ->patch(route('organizations.users.password.update', [$org, $admin]), [
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ]);

        $response->assertRedirect(route('organizations.users.index', $org));
        $response->assertSessionHasErrors('password');
    }

    public function test_cannot_reset_password_for_user_outside_organization(): void
    {
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);

        $response = $this->actingAs($admin)->patch(
            route('organizations.users.password.update', [$org, $outsider]),
            [
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ],
        );

        $response->assertNotFound();
    }
}
