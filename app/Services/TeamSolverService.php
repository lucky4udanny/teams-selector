<?php

namespace App\Services;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Models\Event;
use App\Models\Member;
use App\Models\MemberEventTypeSkill;
use App\Models\Sector;
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
    public function solve(Event $event, array $memberIds): array
    {
        $iterations = max(100, (int) env('APP_MAX_SOLVER_ITERATIONS', 5000));
        $memberIds = array_values(array_unique(array_map(fn (int|string $id): int => (int) $id, $memberIds)));
        sort($memberIds);

        $organization = $event->organization;

        $rules = Rule::query()
            ->where('event_id', $event->id)
            ->orderByDesc('weight')
            ->get();

        $teamSizeRule = $rules->first(fn (Rule $r) => $r->type === RuleType::Size && $r->scope === RuleScope::Team);
        if (! $teamSizeRule) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['Add a size rule (team scope) before generating.'],
            ];
        }

        $teamSize = (int) ($teamSizeRule->config['size'] ?? 0);
        if ($teamSize < 1) {
            return [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => ['The size rule (team scope) must set a positive "size".'],
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

        $groupRule = $rules->first(fn (Rule $r) => $r->type === RuleType::Size && $r->scope === RuleScope::Group);
        $teamsPerGroup = $groupRule ? (int) ($groupRule->config['size'] ?? 1) : 1;
        if ($teamsPerGroup < 1) {
            $teamsPerGroup = 1;
        }

        // Use ceil so a remainder produces one extra (smaller) team rather than blocking.
        $teamCount = (int) ceil($n / $teamSize);

        $historyCache = [];
        $skillByMember = $this->skillLevelsForEvent($event, $memberIds);
        $memberAttributes = $this->memberAttributesForIds($memberIds);

        $scoreState = function (array $teams, array $groups) use ($rules, $event, &$historyCache, $skillByMember, $memberAttributes): array {
            return $this->score(
                $teams,
                $groups,
                $rules,
                $event,
                $historyCache,
                $skillByMember,
                $memberAttributes
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

            if ($best['penalty'] === 0) {
                break;
            }
        }

        // Flag any team that couldn't be filled to the required size.
        // This is structural (penalty 0) — unavoidable when n % teamSize !== 0.
        if ($n % $teamSize !== 0) {
            foreach ($best['teams'] as $tidx => $team) {
                $actual = count($team['member_ids']);
                if ($actual !== $teamSize) {
                    $best['violations'][] = [
                        'rule_id'    => $teamSizeRule->id,
                        'type'       => 'team_size',
                        'scope'      => 'team',
                        'team_index' => $tidx,
                        'expected'   => $teamSize,
                        'actual'     => $actual,
                        'weight'     => $teamSizeRule->weight,
                        'detail'     => "Team ".($tidx + 1)." has {$actual} member(s) instead of the required {$teamSize}.",
                        'penalty'    => 0,
                    ];
                }
            }
        }

        // Flag any group that couldn't be filled to the required size.
        // This is a structural note (penalty 0) — mathematically unavoidable when
        // teamCount % teamsPerGroup !== 0.
        if ($groupRule && $teamsPerGroup > 1) {
            foreach ($best['groups'] as $gidx => $group) {
                $actual = count($group['team_indices']);
                if ($actual < $teamsPerGroup) {
                    $best['violations'][] = [
                        'rule_id'     => $groupRule->id,
                        'type'        => 'group_size',
                        'scope'       => 'group',
                        'group_index' => $gidx,
                        'expected'    => $teamsPerGroup,
                        'actual'      => $actual,
                        'weight'      => $groupRule->weight,
                        'detail'      => "Group ".($gidx + 1)." has {$actual} team(s) instead of the required {$teamsPerGroup}.",
                        'penalty'     => 0,
                    ];
                }
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
    /**
     * @param  list<int>  $memberIds
     * @return array<int, int>
     */
    private function skillLevelsForEvent(Event $event, array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $recorded = MemberEventTypeSkill::query()
            ->where('event_type_id', $event->event_type_id)
            ->whereIn('member_id', $memberIds)
            ->pluck('skill_level', 'member_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Members with no skill record get 50 as a neutral default.
        // 0 is a valid assigned skill level and must not be overridden.
        $result = [];
        foreach ($memberIds as $id) {
            $result[(int) $id] = $recorded[(int) $id] ?? 50;
        }

        return $result;
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<int, array{sector_id: int|null, sector_name: string|null, company: string|null, gender: string|null}>
     */
    private function memberAttributesForIds(array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $members = Member::query()
            ->whereIn('id', $memberIds)
            ->get(['id', 'sector_id', 'company', 'gender']);

        $sectorIds = $members->pluck('sector_id')->filter()->unique()->values()->all();
        $sectorNames = $sectorIds !== []
            ? Sector::whereIn('id', $sectorIds)->pluck('name', 'id')->all()
            : [];

        return $members
            ->keyBy('id')
            ->map(fn (Member $m) => [
                'sector_id'   => $m->sector_id,
                'sector_name' => $m->sector_id !== null ? ($sectorNames[$m->sector_id] ?? null) : null,
                'company'     => ($m->company !== null && $m->company !== '') ? $m->company : null,
                'gender'      => ($m->gender !== null && $m->gender !== '') ? $m->gender : null,
            ])
            ->all();
    }

    /**
     * @param  list<array{member_ids: list<int>}>  $teams
     * @param  list<array{team_indices: list<int>}>  $groups
     * @param  Collection<int, Rule>  $rules
     * @param  array<string, array<string, true>>  $historyCache
     * @param  array<int, int>  $skillByMember
     * @param  array<int, array{sector_id: int|null, sector_name: string|null, company: string|null, gender: string|null}>  $memberAttributes
     * @return array{0: int, 1: list<array<string, mixed>>}
     */
    private function score(array $teams, array $groups, Collection $rules, Event $event, array &$historyCache, array $skillByMember, array $memberAttributes = []): array
    {
        $violations = [];
        $penalty = 0;

        foreach ($rules as $rule) {
            if ($rule->type === RuleType::Size) {
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
                                'rule_id'    => $rule->id,
                                'type'       => 'banned_pair',
                                'scope'      => 'team',
                                'team_index' => $idx,
                                'member_ids' => [$a, $b],
                                'weight'     => $rule->weight,
                                'detail'     => 'Banned pair on team #'.($idx + 1),
                                'penalty'    => $rule->weight,
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
                                'rule_id'     => $rule->id,
                                'type'        => 'banned_pair',
                                'scope'       => 'group',
                                'group_index' => $gidx,
                                'member_ids'  => [$a, $b],
                                'weight'      => $rule->weight,
                                'detail'      => 'Banned pair in group #'.($gidx + 1),
                                'penalty'     => $rule->weight,
                            ];
                            $penalty += $rule->weight;
                        }
                    }
                }
            }

            if ($rule->type === RuleType::PreferredPair) {
                $a = (int) ($rule->config['member_a_id'] ?? 0);
                $b = (int) ($rule->config['member_b_id'] ?? 0);
                if ($a === 0 || $b === 0 || $a === $b) {
                    continue;
                }

                if ($rule->scope === RuleScope::Team) {
                    $together = false;
                    foreach ($teams as $team) {
                        $set = array_flip($team['member_ids']);
                        if (isset($set[$a], $set[$b])) {
                            $together = true;
                            break;
                        }
                    }
                    if (! $together) {
                        $violations[] = [
                            'rule_id'    => $rule->id,
                            'type'       => 'preferred_pair',
                            'scope'      => 'team',
                            'member_ids' => [$a, $b],
                            'weight'     => $rule->weight,
                            'detail'     => 'Preferred pair not on the same team',
                            'penalty'    => $rule->weight,
                        ];
                        $penalty += $rule->weight;
                    }
                } else {
                    $groupMembers = $this->membersByGroup($teams, $groups);
                    $together = false;
                    foreach ($groupMembers as $ids) {
                        $set = array_flip($ids);
                        if (isset($set[$a], $set[$b])) {
                            $together = true;
                            break;
                        }
                    }
                    if (! $together) {
                        $violations[] = [
                            'rule_id'    => $rule->id,
                            'type'       => 'preferred_pair',
                            'scope'      => 'group',
                            'member_ids' => [$a, $b],
                            'weight'     => $rule->weight,
                            'detail'     => 'Preferred pair not in the same group',
                            'penalty'    => $rule->weight,
                        ];
                        $penalty += $rule->weight;
                    }
                }
            }

            if ($rule->type === RuleType::SkillLeveling) {
                $minAvg = (float) ($rule->config['min_avg'] ?? 0);
                $maxAvg = (float) ($rule->config['max_avg'] ?? 100);

                if ($rule->scope === RuleScope::Team) {
                    foreach ($teams as $idx => $team) {
                        $avg = $this->averageSkill($team['member_ids'], $skillByMember);
                        $deviation = $this->skillRangeDeviation($avg, $minAvg, $maxAvg);
                        if ($deviation > 0) {
                            $add = (int) round($rule->weight * $deviation);
                            $violations[] = [
                                'rule_id'    => $rule->id,
                                'type'       => 'skill_leveling',
                                'scope'      => 'team',
                                'team_index' => $idx,
                                'avg_skill'  => $avg,
                                'min_avg'    => $minAvg,
                                'max_avg'    => $maxAvg,
                                'weight'     => $rule->weight,
                                'detail'     => 'Team #'.($idx + 1).' avg skill '.$avg.' outside '.$minAvg.'–'.$maxAvg,
                                'penalty'    => $add,
                            ];
                            $penalty += $add;
                        }
                    }
                } else {
                    $groupMembers = $this->membersByGroup($teams, $groups);
                    foreach ($groupMembers as $gidx => $ids) {
                        $avg = $this->averageSkill($ids, $skillByMember);
                        $deviation = $this->skillRangeDeviation($avg, $minAvg, $maxAvg);
                        if ($deviation > 0) {
                            $add = (int) round($rule->weight * $deviation);
                            $violations[] = [
                                'rule_id'     => $rule->id,
                                'type'        => 'skill_leveling',
                                'scope'       => 'group',
                                'group_index' => $gidx,
                                'avg_skill'   => $avg,
                                'min_avg'     => $minAvg,
                                'max_avg'     => $maxAvg,
                                'weight'      => $rule->weight,
                                'detail'      => 'Group #'.($gidx + 1).' avg skill '.$avg.' outside '.$minAvg.'–'.$maxAvg,
                                'penalty'     => $add,
                            ];
                            $penalty += $add;
                        }
                    }
                }
            }

            if ($rule->type === RuleType::RepeatPair) {
                $priorEventId = (int) ($rule->config['event_id'] ?? 0);
                if ($priorEventId < 1) {
                    continue;
                }
                $cacheKey = $priorEventId.'-'.$rule->scope->value;
                if (! isset($historyCache[$cacheKey])) {
                    $historyCache[$cacheKey] = $this->history->pairsForPriorEvent($priorEventId, $rule->scope);
                }
                $hist = $historyCache[$cacheKey];

                if ($rule->scope === RuleScope::Team) {
                    foreach ($teams as $idx => $team) {
                        foreach ($this->pairsFromTeam($team['member_ids']) as $pk) {
                            if (isset($hist[$pk])) {
                                $violations[] = [
                                    'rule_id'    => $rule->id,
                                    'type'       => 'repeat_pair',
                                    'scope'      => 'team',
                                    'team_index' => $idx,
                                    'member_ids' => array_map('intval', explode('-', $pk)),
                                    'weight'     => $rule->weight,
                                    'detail'     => 'Repeat pair on team #'.($idx + 1),
                                    'penalty'    => $rule->weight,
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
                                    'rule_id'     => $rule->id,
                                    'type'        => 'repeat_pair',
                                    'scope'       => 'group',
                                    'group_index' => $gidx,
                                    'member_ids'  => array_map('intval', explode('-', $pk)),
                                    'weight'      => $rule->weight,
                                    'detail'      => 'Repeat pair in group #'.($gidx + 1),
                                    'penalty'     => $rule->weight,
                                ];
                                $penalty += $rule->weight;
                            }
                        }
                    }
                }
            }

            if ($rule->type === RuleType::MemberAttribute) {
                $attr = $rule->config['attribute'] ?? '';
                $match = $rule->config['match'] ?? 'same';
                $attrLabel = match ($attr) {
                    'sector_id' => 'sector',
                    'gender'    => 'gender',
                    default     => $attr, // 'company' or any future attribute
                };

                $unitSets = $rule->scope === RuleScope::Team
                    ? array_map(fn (array $t) => $t['member_ids'], $teams)
                    : $this->membersByGroup($teams, $groups);

                foreach ($unitSets as $idx => $ids) {
                    $scopeLabel = $rule->scope === RuleScope::Team
                        ? 'team #'.($idx + 1)
                        : 'group #'.($idx + 1);

                    if ($match === 'same') {
                        // Penalise each pair of members that share the same non-null attribute value
                        $byValue = [];
                        foreach ($ids as $mid) {
                            $val = ($memberAttributes[$mid] ?? [])[$attr] ?? null;
                            if ($val === null || $val === '') {
                                continue;
                            }
                            $byValue[(string) $val][] = $mid;
                        }
                        foreach ($byValue as $val => $mids) {
                            $count = count($mids);
                            if ($count < 2) {
                                continue;
                            }
                            $pairs = (int) ($count * ($count - 1) / 2);
                            $add = $rule->weight * $pairs;
                            $valueLabel = $attr === 'sector_id'
                                ? ($memberAttributes[$mids[0]]['sector_name'] ?? null)
                                : $val; // gender / company: use the raw value as label
                            $violations[] = [
                                'rule_id'               => $rule->id,
                                'type'                  => 'member_attribute',
                                'scope'                 => $rule->scope->value,
                                'team_index'            => $rule->scope === RuleScope::Team ? $idx : null,
                                'group_index'           => $rule->scope === RuleScope::Group ? $idx : null,
                                'attribute'             => $attr,
                                'attribute_label'       => $attrLabel,
                                'attribute_value_label' => $valueLabel,
                                'offending_member_ids'  => array_values($mids),
                                'weight'                => $rule->weight,
                                'detail'                => ucfirst($scopeLabel).' has '.$count.' members sharing the same '.$attrLabel,
                                'penalty'               => $add,
                            ];
                            $penalty += $add;
                        }
                    } else {
                        // match === 'different': penalise when more than one distinct non-null value appears
                        $distinctValues = [];
                        foreach ($ids as $mid) {
                            $val = ($memberAttributes[$mid] ?? [])[$attr] ?? null;
                            if ($val === null || $val === '') {
                                continue;
                            }
                            $distinctValues[(string) $val] = true;
                        }
                        $distinctCount = count($distinctValues);
                        if ($distinctCount > 1) {
                            $add = $rule->weight * ($distinctCount - 1);
                            $violations[] = [
                                'rule_id'         => $rule->id,
                                'type'            => 'member_attribute',
                                'scope'           => $rule->scope->value,
                                'team_index'      => $rule->scope === RuleScope::Team ? $idx : null,
                                'group_index'     => $rule->scope === RuleScope::Group ? $idx : null,
                                'attribute'       => $attr,
                                'attribute_label' => $attrLabel,
                                'distinct_count'  => $distinctCount,
                                'weight'          => $rule->weight,
                                'detail'          => ucfirst($scopeLabel).' has '.$distinctCount.' different '.$attrLabel.' values',
                                'penalty'         => $add,
                            ];
                            $penalty += $add;
                        }
                    }
                }
            }
        }

        return [$penalty, $violations];
    }

    /**
     * @param  list<int>  $memberIds
     * @param  array<int, int>  $skillByMember
     */
    private function averageSkill(array $memberIds, array $skillByMember): float
    {
        if ($memberIds === []) {
            return 0.0;
        }

        $sum = 0;
        foreach ($memberIds as $id) {
            $sum += $skillByMember[(int) $id] ?? 50;
        }

        return $sum / count($memberIds);
    }

    private function skillRangeDeviation(float $avg, float $minAvg, float $maxAvg): float
    {
        if ($avg < $minAvg) {
            return ($minAvg - $avg) / 100;
        }

        if ($avg > $maxAvg) {
            return ($avg - $maxAvg) / 100;
        }

        return 0.0;
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
            foreach ($group['team_indices'] ?? [] as $ti) {
                $ti = (int) $ti;
                foreach (($teams[$ti] ?? [])['member_ids'] ?? [] as $mid) {
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
