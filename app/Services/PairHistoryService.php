<?php

namespace App\Services;

use App\Models\ApprovedSelection;
use App\Models\Organization;

class PairHistoryService
{
    /**
     * @return array<string, true> canonical pair keys "min-max"
     */
    public function teamPairsInWindow(Organization $organization, int $window): array
    {
        $selections = ApprovedSelection::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit($window)
            ->get(['snapshot']);

        $pairs = [];

        foreach ($selections as $sel) {
            $teams = $sel->snapshot['teams'] ?? [];
            foreach ($teams as $team) {
                $ids = $team['member_ids'] ?? [];
                foreach ($this->pairsFromList($ids) as $key) {
                    $pairs[$key] = true;
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, true>
     */
    public function groupPairsInWindow(Organization $organization, int $window): array
    {
        $selections = ApprovedSelection::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit($window)
            ->get(['snapshot']);

        $pairs = [];

        foreach ($selections as $sel) {
            $teams = $sel->snapshot['teams'] ?? [];
            $groups = $sel->snapshot['groups'] ?? [];
            foreach ($groups as $group) {
                $indices = $group['team_indices'] ?? [];
                $memberIds = [];
                foreach ($indices as $ti) {
                    $ti = (int) $ti;
                    $ids = $teams[$ti]['member_ids'] ?? [];
                    foreach ($ids as $id) {
                        $memberIds[(int) $id] = true;
                    }
                }
                $list = array_keys($memberIds);
                sort($list);
                foreach ($this->pairsFromList($list) as $key) {
                    $pairs[$key] = true;
                }
            }
        }

        return $pairs;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function pairsFromList(array $ids): array
    {
        $n = count($ids);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = (int) $ids[$i];
                $b = (int) $ids[$j];
                if ($a === $b) {
                    continue;
                }
                $min = min($a, $b);
                $max = max($a, $b);
                $out[] = $min.'-'.$max;
            }
        }

        return $out;
    }
}
