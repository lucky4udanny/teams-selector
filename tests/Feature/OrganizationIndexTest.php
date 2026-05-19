<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_single_organization_is_redirected_to_that_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Viewer->value]);

        $response = $this->actingAs($user)->get(route('organizations.index'));

        $response->assertRedirect(route('organizations.show', $org));
    }

    public function test_user_with_multiple_organizations_sees_the_picker(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::factory()->create(['name' => 'Alpha Club']);
        $orgB = Organization::factory()->create(['name' => 'Beta Club']);
        $orgA->users()->attach($user->id, ['role' => OrganizationRole::Viewer->value]);
        $orgB->users()->attach($user->id, ['role' => OrganizationRole::Viewer->value]);

        $response = $this->actingAs($user)->get(route('organizations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 2));
    }

    public function test_super_admin_with_single_membership_still_sees_all_organizations(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $org = Organization::factory()->create();
        Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Admin->value]);

        $response = $this->actingAs($user)->get(route('organizations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 2));
    }

    public function test_dashboard_forwards_single_org_user_to_their_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Viewer->value]);

        $response = $this->actingAs($user)->followingRedirects()->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Organizations/Show'));
    }
}
