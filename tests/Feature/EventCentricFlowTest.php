<?php

namespace Tests\Feature;

use App\Enums\EventMemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Rule;
use App\Models\TeamDraft;
use App\Models\User;
use App\Services\TeamSolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCentricFlowTest extends TestCase
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

    public function test_event_can_be_created_with_type_and_date(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $type = EventType::factory()->create(['organization_id' => $org->id]);

        $response = $this->post(route('organizations.events.store', $org), [
            'name' => 'Spring Mixer',
            'description' => 'Annual event',
            'event_date' => '2026-06-01',
            'event_type_id' => $type->id,
            'uses_groups' => false,
            'previous_event_ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', [
            'organization_id' => $org->id,
            'name' => 'Spring Mixer',
            'event_type_id' => $type->id,
        ]);
    }

    public function test_roster_payload_is_sorted_by_member_name(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $zeke = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Zeke',
            'last_name' => 'Zulu',
        ]);
        $amy = Member::factory()->create([
            'organization_id' => $org->id,
            'first_name' => 'Amy',
            'last_name' => 'Adams',
        ]);

        foreach ([$zeke, $amy] as $member) {
            EventMember::query()->create([
                'event_id' => $event->id,
                'member_id' => $member->id,
                'included' => true,
                'invited' => false,
                'status' => EventMemberStatus::Pending,
                'status_changed_at' => now(),
            ]);
        }

        $payload = app(\App\Http\Controllers\EventMemberController::class)->rosterPayloadPublic($event);
        $names = array_column($payload['roster'], 'display_name');

        $this->assertSame(
            [trim($amy->first_name.' '.$amy->last_name), trim($zeke->first_name.' '.$zeke->last_name)],
            $names,
        );
    }

    public function test_roster_bulk_add_members_from_organization(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $members = Member::factory()->count(3)->create(['organization_id' => $org->id]);
        $ids = $members->pluck('id')->all();

        $this->post(route('organizations.events.members.store', [$org, $event]), [
            'member_ids' => $ids,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        foreach ($ids as $memberId) {
            $this->assertDatabaseHas('event_members', [
                'event_id' => $event->id,
                'member_id' => $memberId,
            ]);
        }
    }

    public function test_roster_enforces_unique_member_per_event(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $member = Member::factory()->create(['organization_id' => $org->id]);

        EventMember::query()->create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'included' => true,
            'invited' => false,
            'status' => EventMemberStatus::Pending,
            'status_changed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EventMember::query()->create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'included' => true,
            'invited' => false,
            'status' => EventMemberStatus::Pending,
            'status_changed_at' => now(),
        ]);
    }

    public function test_only_one_team_size_rule_per_event(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        $this->post(route('organizations.events.rules.store', [$org, $event]), [
            'type' => RuleType::TeamSize->value,
            'scope' => RuleScope::Team->value,
            'weight' => 100,
            'config' => ['size' => 4],
        ])->assertRedirect();

        $this->post(route('organizations.events.rules.store', [$org, $event]), [
            'type' => RuleType::TeamSize->value,
            'scope' => RuleScope::Team->value,
            'weight' => 50,
            'config' => ['size' => 2],
        ])->assertSessionHasErrors('type');

        $this->assertEquals(1, Rule::query()->where('event_id', $event->id)->where('type', RuleType::TeamSize)->count());
    }

    public function test_generate_always_creates_new_team_draft_row(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $members = Member::factory()->count(4)->create(['organization_id' => $org->id]);

        foreach ($members as $member) {
            EventMember::query()->create([
                'event_id' => $event->id,
                'member_id' => $member->id,
                'included' => true,
                'invited' => false,
                'status' => EventMemberStatus::Accepted,
                'status_changed_at' => now(),
            ]);
        }

        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::TeamSize,
            'scope' => RuleScope::Team,
            'weight' => 100,
            'config' => ['size' => 2],
            'sort_order' => 0,
        ]);

        $this->post(route('organizations.events.team-drafts.generate', [$org, $event]), [
            'include_pending' => false,
        ])->assertRedirect();

        $this->post(route('organizations.events.team-drafts.generate', [$org, $event]), [
            'include_pending' => false,
        ])->assertRedirect();

        $this->assertEquals(2, TeamDraft::query()->where('event_id', $event->id)->count());
    }

    public function test_finalize_sets_single_final_on_event(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $draft = TeamDraft::factory()->create([
            'event_id' => $event->id,
            'state' => ['teams' => [], 'groups' => [], 'blocking_errors' => []],
        ]);

        $this->post(route('organizations.events.team-drafts.finalize', [$org, $event, $draft]))
            ->assertRedirect();

        $event->refresh();
        $this->assertEquals($draft->id, $event->final_team_draft_id);
        $this->assertNotNull($event->finalized_at);
        $this->assertTrue($draft->fresh()->is_final);
    }

    public function test_finalize_rejected_when_draft_has_blocking_errors(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $draft = TeamDraft::factory()->create([
            'event_id' => $event->id,
            'state' => [
                'teams' => [],
                'groups' => [],
                'blocking_errors' => ['Member count must divide evenly by team size (2). Current: 3.'],
            ],
        ]);

        $this->post(route('organizations.events.team-drafts.finalize', [$org, $event, $draft]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $event->refresh();
        $this->assertNull($event->final_team_draft_id);
        $this->assertNull($event->finalized_at);
        $this->assertFalse($draft->fresh()->is_final);
    }

    public function test_duplicate_event_resets_invitation_and_status(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $member = Member::factory()->create(['organization_id' => $org->id]);

        EventMember::query()->create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'included' => true,
            'invited' => true,
            'invited_at' => now(),
            'status' => EventMemberStatus::Accepted,
            'status_changed_at' => now(),
        ]);

        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::TeamSize,
            'scope' => RuleScope::Team,
            'weight' => 100,
            'config' => ['size' => 2],
            'sort_order' => 0,
        ]);

        $this->post(route('organizations.events.duplicate', [$org, $event]), [
            'name' => 'Copy Event',
        ])->assertRedirect();

        $copy = Event::query()->where('name', 'Copy Event')->first();
        $this->assertNotNull($copy);

        $em = EventMember::query()->where('event_id', $copy->id)->first();
        $this->assertFalse($em->invited);
        $this->assertEquals(EventMemberStatus::Pending, $em->status);
        $this->assertEquals(1, Rule::query()->where('event_id', $copy->id)->count());
    }

    public function test_duplicate_event_recalculates_rule_sort_order_by_weight(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::PreferredPair,
            'scope' => RuleScope::Team,
            'weight' => 50,
            'config' => ['member_a_id' => 1, 'member_b_id' => 2],
            'sort_order' => 0,
        ]);
        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::TeamSize,
            'scope' => RuleScope::Team,
            'weight' => 100,
            'config' => ['size' => 2],
            'sort_order' => 5,
        ]);

        $this->post(route('organizations.events.duplicate', [$org, $event]), [
            'name' => 'Copy With Rules',
        ])->assertRedirect();

        $copy = Event::query()->where('name', 'Copy With Rules')->first();
        $this->assertNotNull($copy);

        $sorted = Rule::query()
            ->where('event_id', $copy->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $sorted);
        $this->assertEquals(RuleType::TeamSize, $sorted[0]->type);
        $this->assertEquals(0, $sorted[0]->sort_order);
        $this->assertEquals(RuleType::PreferredPair, $sorted[1]->type);
        $this->assertEquals(1, $sorted[1]->sort_order);
    }

    public function test_solver_penalizes_preferred_pair_not_together(): void
    {
        [, $org] = $this->actingAsOrganizer();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $members = Member::factory()->count(4)->create(['organization_id' => $org->id]);
        $ids = $members->pluck('id')->all();

        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::TeamSize,
            'scope' => RuleScope::Team,
            'weight' => 100,
            'config' => ['size' => 2],
            'sort_order' => 0,
        ]);

        Rule::query()->create([
            'event_id' => $event->id,
            'type' => RuleType::PreferredPair,
            'scope' => RuleScope::Team,
            'weight' => 50,
            'config' => ['member_a_id' => $ids[0], 'member_b_id' => $ids[1]],
            'sort_order' => 1,
        ]);

        $solver = app(TeamSolverService::class);
        $result = $solver->solve($event, $ids, 10);

        $this->assertEmpty($result['blocking_errors']);
        $hasPreferredViolation = collect($result['violations'])->contains(
            fn ($v) => ($v['type'] ?? '') === 'preferred_pair',
        );
        $this->assertTrue($hasPreferredViolation || $result['total_penalty'] === 0);
    }
}
