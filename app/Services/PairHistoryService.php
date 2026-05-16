<?php

namespace App\Services;

use App\Enums\RuleScope;
use App\Models\Event;
use App\Models\TeamDraft;

class PairHistoryService
{
    /**
     * @return array<string, true> canonical pair keys "min-max"
     */
    public function pairsForPriorEvent(int $priorEventId, RuleScope $scope): array
    {
        $event = Event::query()->find($priorEventId);
        if (! $event || ! $event->final_team_draft_id) {
            return [];
        }

        $draft = TeamDraft::query()->find($event->final_team_draft_id);
        if (! $draft || ! is_array($draft->state)) {
            return [];
        }

        $teams = $draft->state['teams'] ?? [];
        $groups = $draft->state['groups'] ?? [];

        if ($scope === RuleScope::Team) {
            return $this->pairsFromTeams($teams);
        }

        return $this->pairsFromGroups($teams, $groups);
    }

    /**
     * @param  list<array{member_ids: list<int>}>  $teams
     * @return array<string, true>
     */
    private function pairsFromTeams(array $teams): array
    {
        $pairs = [];
        foreach ($teams as $team) {
            foreach ($this->pairsFromMemberList($team['member_ids'] ?? []) as $key) {
                $pairs[$key] = true;
            }
        }

        return $pairs;
    }

    /**
     * @param  list<array{member_ids: list<int>}>  $teams
     * @param  list<array{team_indices: list<int>}>  $groups
     * @return array<string, true>
     */
    private function pairsFromGroups(array $teams, array $groups): array
    {
        $pairs = [];
        foreach ($groups as $group) {
            $ids = [];
            foreach ($group['team_indices'] ?? [] as $ti) {
                foreach (($teams[(int) $ti] ?? [])['member_ids'] ?? [] as $mid) {
                    $ids[] = (int) $mid;
                }
            }
            foreach ($this->pairsFromMemberList($ids) as $key) {
                $pairs[$key] = true;
            }
        }

        return $pairs;
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<string>
     */
    private function pairsFromMemberList(array $memberIds): array
    {
        $n = count($memberIds);
        $keys = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $keys[] = $this->pairKey((int) $memberIds[$i], (int) $memberIds[$j]);
            }
        }

        return $keys;
    }

    private function pairKey(int $a, int $b): string
    {
        $min = min($a, $b);
        $max = max($a, $b);

        return $min.'-'.$max;
    }
}
