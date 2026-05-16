<?php

namespace App\Services;

use App\Enums\EventMemberStatus;
use App\Enums\RuleType;
use App\Models\Event;
use App\Models\MemberEventTypeSkill;
use App\Models\Rule;

class EventPlanningConflictService
{
    /**
     * @return list<array{type: string, message: string}>
     */
    public function analyze(Event $event): array
    {
        $warnings = [];

        foreach ($this->bannedPreferredOverlaps($event) as $msg) {
            $warnings[] = ['type' => 'banned_preferred_overlap', 'message' => $msg];
        }

        foreach ($this->impossibleSkillRanges($event) as $msg) {
            $warnings[] = ['type' => 'impossible_skill_range', 'message' => $msg];
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function bannedPreferredOverlaps(Event $event): array
    {
        $rules = Rule::query()->where('event_id', $event->id)->get();
        $banned = [];
        $preferred = [];

        foreach ($rules as $rule) {
            if ($rule->type === RuleType::BannedPair) {
                $a = (int) ($rule->config['member_a_id'] ?? 0);
                $b = (int) ($rule->config['member_b_id'] ?? 0);
                if ($a > 0 && $b > 0 && $a !== $b) {
                    $banned[$this->pairKey($a, $b)] = true;
                }
            }
            if ($rule->type === RuleType::PreferredPair) {
                $a = (int) ($rule->config['member_a_id'] ?? 0);
                $b = (int) ($rule->config['member_b_id'] ?? 0);
                if ($a > 0 && $b > 0 && $a !== $b) {
                    $preferred[$this->pairKey($a, $b)] = true;
                }
            }
        }

        $out = [];
        foreach (array_keys($banned) as $k) {
            if (isset($preferred[$k])) {
                $out[] = 'The same pair is both banned and preferred (members '.$k.').';
            }
        }

        return $out;
    }

    /**
     * Heuristic: with current roster skills, team averages may be unable to land in the rule band.
     *
     * @return list<string>
     */
    private function impossibleSkillRanges(Event $event): array
    {
        $rules = Rule::query()
            ->where('event_id', $event->id)
            ->where('type', RuleType::SkillLeveling)
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $memberIds = $event->eventMembers()
            ->where('included', true)
            ->whereIn('status', [EventMemberStatus::Accepted->value, EventMemberStatus::Pending->value])
            ->pluck('member_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($memberIds === []) {
            return [];
        }

        $skills = MemberEventTypeSkill::query()
            ->where('event_type_id', $event->event_type_id)
            ->whereIn('member_id', $memberIds)
            ->pluck('skill_level', 'member_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $values = [];
        foreach ($memberIds as $mid) {
            $values[] = $skills[$mid] ?? 0;
        }

        $gMin = min($values);
        $gMax = max($values);

        $messages = [];
        foreach ($rules as $rule) {
            $minAvg = (float) ($rule->config['min_avg'] ?? 0);
            $maxAvg = (float) ($rule->config['max_avg'] ?? 100);

            if ($minAvg > $maxAvg) {
                $messages[] = 'Skill leveling rule #'.$rule->id.' has min_avg greater than max_avg.';

                continue;
            }

            if ($minAvg > $gMax) {
                $messages[] = 'Skill leveling rule #'.$rule->id.' requires team average at least '.$minAvg.', but no member exceeds '.$gMax.'.';
            }

            if ($maxAvg < $gMin) {
                $messages[] = 'Skill leveling rule #'.$rule->id.' requires team average at most '.$maxAvg.', but every member is at least '.$gMin.'.';
            }
        }

        return array_values(array_unique($messages));
    }

    private function pairKey(int $a, int $b): string
    {
        $min = min($a, $b);
        $max = max($a, $b);

        return $min.'-'.$max;
    }
}
