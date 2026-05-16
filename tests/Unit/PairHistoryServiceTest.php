<?php

namespace Tests\Unit;

use App\Enums\RuleScope;
use App\Models\Event;
use App\Models\TeamDraft;
use App\Services\PairHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PairHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_scope_skips_invalid_team_indices_without_error(): void
    {
        $event = Event::factory()->create();
        $draft = TeamDraft::factory()->create([
            'event_id' => $event->id,
            'state' => [
                'teams' => [
                    ['member_ids' => [1, 2]],
                    ['member_ids' => [3, 4]],
                ],
                'groups' => [
                    ['team_indices' => [0, 99]],
                ],
            ],
            'is_final' => true,
        ]);
        $event->update([
            'final_team_draft_id' => $draft->id,
            'finalized_at' => now(),
        ]);

        $pairs = app(PairHistoryService::class)->pairsForPriorEvent($event->id, RuleScope::Group);

        $this->assertSame(['1-2' => true], $pairs);
    }

    public function test_team_scope_builds_pairs_from_each_team(): void
    {
        $event = Event::factory()->create();
        $draft = TeamDraft::factory()->create([
            'event_id' => $event->id,
            'state' => [
                'teams' => [
                    ['member_ids' => [10, 11]],
                    ['member_ids' => [12]],
                ],
                'groups' => [],
            ],
            'is_final' => true,
        ]);
        $event->update([
            'final_team_draft_id' => $draft->id,
            'finalized_at' => now(),
        ]);

        $pairs = app(PairHistoryService::class)->pairsForPriorEvent($event->id, RuleScope::Team);

        $this->assertSame(['10-11' => true], $pairs);
    }
}
