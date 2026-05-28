<?php

namespace Tests\Feature;

use App\Enums\EventMemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use App\Services\RosterFilterCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRosterExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOrganizer(): array
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $this->actingAs($user);

        return [$user, $org];
    }

    public function test_roster_csv_export_respects_status_filter(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $accepted = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Amy',
            'last_name' => 'Accepted',
        ]);
        $pending = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Pat',
            'last_name' => 'Pending',
        ]);

        EventMember::query()->create([
            'event_id' => $event->id,
            'member_id' => $accepted->id,
            'included' => true,
            'invited' => true,
            'status' => EventMemberStatus::Accepted,
            'status_changed_at' => now(),
        ]);
        EventMember::query()->create([
            'event_id' => $event->id,
            'member_id' => $pending->id,
            'included' => true,
            'invited' => false,
            'status' => EventMemberStatus::Pending,
            'status_changed_at' => now(),
        ]);

        $response = $this->get(route('organizations.events.roster.export.csv', [$org, $event]).'?status=accepted');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('Amy', $body);
        $this->assertStringNotContainsString('Pat', $body);
        $this->assertStringContainsString('Accepted', $body);
    }

    public function test_roster_csv_export_respects_search_and_gender_filters(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $sector = Sector::factory()->create(['organization_id' => $org->id, 'name' => 'Finance']);
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $match = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'gender' => 'female',
            'sector_id' => $sector->id,
        ]);
        $other = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Sam',
            'last_name' => 'Smith',
            'gender' => 'male',
        ]);

        foreach ([$match, $other] as $member) {
            EventMember::query()->create([
                'event_id' => $event->id,
                'member_id' => $member->id,
                'included' => true,
                'invited' => false,
                'status' => EventMemberStatus::Pending,
                'status_changed_at' => now(),
            ]);
        }

        $query = http_build_query([
            'search' => 'jordan',
            'gender' => 'female',
            'sector_id' => $sector->id,
        ]);

        $response = $this->get(route('organizations.events.roster.export.csv', [$org, $event]).'?'.$query);

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('Jordan', $body);
        $this->assertStringNotContainsString('Sam', $body);
        $this->assertStringContainsString('Finance', $body);
    }

    public function test_roster_csv_export_denied_for_non_member(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        $this->get(route('organizations.events.roster.export.csv', [$org, $event]))
            ->assertForbidden();
    }

    public function test_roster_filter_criteria_normalizes_invited_sentinel_values(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [
            'invited' => RosterFilterCriteria::FILTER_INVITED_YES,
            'sector_id' => RosterFilterCriteria::FILTER_UNSPECIFIED,
        ]);

        $criteria = RosterFilterCriteria::fromRequest($request);

        $this->assertSame(RosterFilterCriteria::FILTER_INVITED_YES, $criteria->invited);
        $this->assertSame(RosterFilterCriteria::FILTER_UNSPECIFIED, $criteria->sectorId);
    }
}
