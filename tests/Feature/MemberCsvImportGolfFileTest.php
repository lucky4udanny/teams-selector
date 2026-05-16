<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCsvImportGolfFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_golf_tournament_fixture_imports_members(): void
    {
        $fixture = base_path('Golf tournament 2.csv');
        if (! is_readable($fixture)) {
            $this->markTestSkipped('Fixture Golf tournament 2.csv not present.');
        }

        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $response = $this->actingAs($user)->post(
            route('organizations.members.import', $org),
            [
                'file' => new \Illuminate\Http\UploadedFile(
                    $fixture,
                    'Golf tournament 2.csv',
                    'text/csv',
                    null,
                    true,
                ),
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $this->assertGreaterThanOrEqual(3, Member::query()->where('organization_id', $org->id)->count());
        $this->assertDatabaseHas('members', [
            'organization_id' => $org->id,
            'first_name' => 'Aaron',
            'last_name' => 'Dyck',
            'email' => 'adyck@underhillsfarmsupply.ca',
        ]);
    }
}
