<?php

namespace App\Services;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Models\Organization;
use App\Models\Rule;
use Illuminate\Support\Collection;

class TeamSolverService
{
    public function __construct(
        private PairHistoryService $history,
    ) {}

    /**
     * @param  list<int>  $memberIds
     * @return array{teams: list<array{member_ids: list<int>}>, groups: list<array{team_indices: list<int>}>|array{}, violations: list<array<string, mixed>>, total_penalty: int, blocking_errors: list<string>}
     */
    public function solve(Organization $organization, array $memberIds, int $iterations = 4000): array
    {
        $memberIds = array_values(array_unique(array_map(fn (int|string $id): int => (int) $id, $memberIds)));
        sort($memberIds);

        $rules = Rule::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('weight')
            ->get();

        $teamSizeRule = $rules->first(fn (Rule $r) => $r->type === RuleType::TeamSize);
        if (! $teamSizeRule) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['Add a team size rule before generating.'],
            ];
        }

        $teamSize = (int) ($teamSizeRule->config['size'] ?? 0);
        if ($teamSize < 1) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['Team size rule must set a positive "size".'],
            ];
        }

        $n = count($memberIds);
        if ($n === 0) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['No active members to place.'],
            ];
        }

        if ($n % $teamSize !== 0) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['Member count must divide evenly by team size ('.$teamSize.'). Current: '.$n.'.'],
            ];
        }

        $groupRule = $rules->first(fn (Rule $r) => $r->type === RuleType::GroupSize);
        $teamsPerGroup = $groupRule ? (int) ($groupRule->config['teams_per_group'] ?? 1) : 1;
        if ($teamsPerGroup < 1) {
            $teamsPerGroup = 1;
        }

        $teamCount = (int) ($n / $teamSize);
        if ($groupRule && $teamCount % $teamsPerGroup !== 0) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['Team count ('.$teamCount.') must divide evenly by teams per group ('.$teamsPerGroup.').'],
            ];
        }

        $historyCache = [];

        $scoreState = function (array $teams, array $groups) use ($rules, $organization, &$historyCache): array {
            return $this->score(
                $teams,
                $groups,
                $rules,
                $organization,
                $historyCache
            );
        };

        $teams = $this->randomPartition($memberIds, $teamSize);
        $groups = $this->buildGroups(count($teams), $teamsPerGroup);

        [$penalty, $violations] = $scoreState($teams, $groups);
        $best = ['teams' => $teams, 'groups' => $groups, 'penalty' => $penalty, 'violations' => $violations];

        for ($k = 0; $k < $iterations; $k++) {
            $teams = $best['teams'];
            $groups = $best['groups'];
            if (count($teams) < 2) {
                break;
            }
            $ti = random_int(0, count($teams) - 1);
            $tj = random_int(0, count($teams) - 1);
            if ($ti === $tj) {
                continue;
            }
            if (count($teams[$ti]['member_ids']) === 0 || count($teams[$tj]['member_ids']) === 0) {
                continue;
            }
            $mi = random_int(0, count($teams[$ti]['member_ids']) - 1);
            $mj = random_int(0, count($teams[$tj]['member_ids']) - 1);

            $next = $teams;
            $a = $next[$ti]['member_ids'][$mi];
            $b = $next[$tj]['member_ids'][$mj];
            $next[$ti]['member_ids'][$mi] = $b;
            $next[$tj]['member_ids'][$mj] = $a;

            [$p, $v] = $scoreState($next, $groups);
            if ($p < $best['penalty'] || ($p === $best['penalty'] && random_int(0, 100) < 5)) {
                $best = ['teams' => $next, 'groups' => $groups, 'penalty' => $p, 'violations' => $v];
            }
        }

        return [
            'teams' => $best['teams'],
            'groups' => $best['groups'],
            'violations' => $best['violations'],
            'total_penalty' => $best['penalty'],
            'blocking_errors' => [],
        ];
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<array{member_ids: list<int>}>
     */
    private function randomPartition(array $memberIds, int $teamSize): array
    {
        shuffle($memberIds);
        $teams = [];
        for ($i = 0; $i < count($memberIds); $i += $teamSize) {
            $chunk = array_slice($memberIds, $i, $teamSize);
            $teams[] = ['member_ids' => array_values($chunk)];
        }

        return $teams;
    }

    /**
     * @return list<array{team_indices: list<int>}>
     */
    private function buildGroups(int $teamCount, int $teamsPerGroup): array
    {
        $groups = [];
        for ($i = 0; $i < $teamCount; $i += $teamsPerGroup) {
            $idx = [];
            for ($j = 0; $j < $teamsPerGroup && $i + $j < $teamCount; $j++) {
                $idx[] = $i + $j;
            }
            $groups[] = ['team_indices' => $idx];
        }

        return $groups;
    }

    /**
     * @param  list<array{member_ids: list<int>}>  $teams
     * @param  list<array{team_indices: list<int>}>  $groups
     * @param  Collection<int, Rule>  $rules
     * @param  array<string, array<string, true>>  $historyCache
     * @return array{0: int, 1: list<array<string, mixed>>}
     */
    private function score(array $teams, array $groups, Collection $rules, Organization $organization, array &$historyCache): array
    {
        $violations = [];
        $penalty = 0;

        foreach ($rules as $rule) {
            if ($rule->type === RuleType::TeamSize || $rule->type === RuleType::GroupSize) {
                continue;
            }

            if ($rule->type === RuleType::BannedPair) {
                $a = (int) ($rule->config['member_a_id'] ?? 0);
                $b = (int) ($rule->config['member_b_id'] ?? 0);
                if ($a === 0 || $b === 0 || $a === $b) {
                    continue;
                }
                $pairKey = $this->pairKey($a, $b);

                if ($rule->scope === RuleScope::Team) {
                    foreach ($teams as $idx => $team) {
                        $set = array_flip($team['member_ids']);
                        if (isset($set[$a], $set[$b])) {
                            $violations[] = [
                                'rule_id' => $rule->id,
                                'type' => 'banned_pair',
                                'scope' => 'team',
                                'weight' => $rule->weight,
                                'detail' => 'Banned pair on team #'.($idx + 1),
                                'penalty' => $rule->weight,
                            ];
                            $penalty += $rule->weight;
                        }
                    }
                } else {
                    $groupMembers = $this->membersByGroup($teams, $groups);
                    foreach ($groupMembers as $gidx => $ids) {
                        $set = array_flip($ids);
                        if (isset($set[$a], $set[$b])) {
                            $violations[] = [
                                'rule_id' => $rule->id,
                                'type' => 'banned_pair',
                                'scope' => 'group',
                                'weight' => $rule->weight,
                                'detail' => 'Banned pair in group #'.($gidx + 1),
                                'penalty' => $rule->weight,
                            ];
                            $penalty += $rule->weight;
                        }
                    }
                }
            }

            if ($rule->type === RuleType::RepeatPair) {
                $window = (int) ($rule->config['window'] ?? 1);
                $cacheKey = $organization->id.'-'.$rule->scope->value.'-'.$window;
                if (! isset($historyCache[$cacheKey])) {
                    $historyCache[$cacheKey] = $rule->scope === RuleScope::Team
                        ? $this->history->teamPairsInWindow($organization, $window)
                        : $this->history->groupPairsInWindow($organization, $window);
                }
                $hist = $historyCache[$cacheKey];

                if ($rule->scope === RuleScope::Team) {
                    foreach ($teams as $idx => $team) {
                        foreach ($this->pairsFromTeam($team['member_ids']) as $pk) {
                            if (isset($hist[$pk])) {
                                $violations[] = [
                                    'rule_id' => $rule->id,
                                    'type' => 'repeat_pair',
                                    'scope' => 'team',
                                    'weight' => $rule->weight,
                                    'detail' => 'Repeat pair on team #'.($idx + 1).' (last '.$window.' approvals)',
                                    'penalty' => $rule->weight,
                                ];
                                $penalty += $rule->weight;
                            }
                        }
                    }
                } else {
                    $groupMembers = $this->membersByGroup($teams, $groups);
                    foreach ($groupMembers as $gidx => $ids) {
                        foreach ($this->pairsFromMemberList($ids) as $pk) {
                            if (isset($hist[$pk])) {
                                $violations[] = [
                                    'rule_id' => $rule->id,
                                    'type' => 'repeat_pair',
                                    'scope' => 'group',
                                    'weight' => $rule->weight,
                                    'detail' => 'Repeat pair in group #'.($gidx + 1).' (last '.$window.' approvals)',
                                    'penalty' => $rule->weight,
                                ];
                                $penalty += $rule->weight;
                            }
                        }
                    }
                }
            }
        }

        return [$penalty, $violations];
    }

    /**
     * @param  list<array{member_ids: list<int>}>  $teams
     * @param  list<array{team_indices: list<int>}>  $groups
     * @return list<list<int>>
     */
    private function membersByGroup(array $teams, array $groups): array
    {
        $out = [];
        foreach ($groups as $gidx => $group) {
            $ids = [];
            foreach ($group['team_indices'] as $ti) {
                $ti = (int) $ti;
                foreach ($teams[$ti]['member_ids'] ?? [] as $mid) {
                    $ids[] = (int) $mid;
                }
            }
            $out[$gidx] = $ids;
        }

        return $out;
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<string>
     */
    private function pairsFromTeam(array $memberIds): array
    {
        return $this->pairsFromMemberList($memberIds);
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
