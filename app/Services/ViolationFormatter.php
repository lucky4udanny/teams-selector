<?php

namespace App\Services;

use App\Models\Member;

class ViolationFormatter
{
    /**
     * @return array<int, string>
     */
    public function memberNamesForOrganization(int $organizationId): array
    {
        return Member::query()
            ->where('organization_id', $organizationId)
            ->get()
            ->mapWithKeys(fn (Member $m) => [$m->id => $m->displayName()])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<int, string>  $memberNames
     * @return array<string, mixed>
     */
    public function enrichDraftState(array $state, array $memberNames): array
    {
        if (! is_array($state['violations'] ?? null)) {
            return $state;
        }

        $state['violations'] = $this->enrich($state['violations'], $memberNames);

        return $state;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $violations
     * @param  array<int, string>  $memberNames
     * @return list<array<string, mixed>>
     */
    public function enrich(iterable $violations, array $memberNames): array
    {
        $out = [];
        foreach ($violations as $violation) {
            if (! is_array($violation)) {
                continue;
            }
            $copy = $violation;
            $copy['formatted'] = $this->format($violation, $memberNames);
            $out[] = $copy;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $violation
     * @param  array<int, string>  $memberNames
     */
    public function format(array $violation, array $memberNames): string
    {
        $mName = fn (mixed $id): string => $memberNames[(int) $id] ?? 'Member #'.$id;

        $message = match ($violation['type'] ?? '') {
            'skill_leveling' => $this->formatSkillLeveling($violation),
            'banned_pair' => $this->formatBannedPair($violation, $mName),
            'preferred_pair' => $this->formatPreferredPair($violation, $mName),
            'repeat_pair' => $this->formatRepeatPair($violation, $mName),
            'team_size' => $this->formatTeamSize($violation),
            'group_size' => $this->formatGroupSize($violation),
            'member_attribute' => $this->formatMemberAttribute($violation, $mName),
            default => (string) ($violation['detail'] ?? $violation['type'] ?? 'Violation'),
        };

        return $this->appendScoreSuffix($message, $violation);
    }

    /**
     * Prefix spreadsheet-dangerous string cell values.
     */
    public function sanitizeSpreadsheetCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    public function sanitizeSpreadsheetRow(array $row): array
    {
        return array_map(fn (mixed $cell) => $this->sanitizeSpreadsheetCell($cell), $row);
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function formatSkillLeveling(array $violation): string
    {
        if (isset($violation['avg_skill'], $violation['min_avg'], $violation['max_avg'])) {
            $avg = round((float) $violation['avg_skill'], 1);

            return "Skill levelling: avg skill {$avg} is outside {$violation['min_avg']}–{$violation['max_avg']}";
        }

        return (string) ($violation['detail'] ?? 'Skill levelling violation');
    }

    /**
     * @param  array<string, mixed>  $violation
     * @param  callable(mixed): string  $mName
     */
    private function formatBannedPair(array $violation, callable $mName): string
    {
        $ids = $violation['member_ids'] ?? [];
        if (is_array($ids) && count($ids) === 2) {
            return $mName($ids[0]).' and '.$mName($ids[1]).' are a banned pair';
        }

        return 'Banned pair';
    }

    /**
     * @param  array<string, mixed>  $violation
     * @param  callable(mixed): string  $mName
     */
    private function formatPreferredPair(array $violation, callable $mName): string
    {
        $scope = ($violation['scope'] ?? '') === 'group' ? 'group' : 'team';
        $ids = $violation['member_ids'] ?? [];
        if (is_array($ids) && count($ids) === 2) {
            return $mName($ids[0]).' and '.$mName($ids[1])." should be in the same {$scope} but are separated";
        }

        return "Preferred pair separated ({$scope}-level)";
    }

    /**
     * @param  array<string, mixed>  $violation
     * @param  callable(mixed): string  $mName
     */
    private function formatRepeatPair(array $violation, callable $mName): string
    {
        $ids = $violation['member_ids'] ?? [];
        if (is_array($ids) && count($ids) === 2) {
            return $mName($ids[0]).' and '.$mName($ids[1]).' were paired in a prior event';
        }

        return 'Repeat pair from a prior event';
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function formatTeamSize(array $violation): string
    {
        $actual = $violation['actual'] ?? '?';
        $expected = $violation['expected'] ?? '?';

        return "Team has {$actual} member(s) — rule requires {$expected} (unavoidable with current member count)";
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function formatGroupSize(array $violation): string
    {
        $actual = $violation['actual'] ?? '?';
        $expected = $violation['expected'] ?? '?';

        return "Group has {$actual} team(s) — rule requires {$expected} (unavoidable with current member count)";
    }

    /**
     * @param  array<string, mixed>  $violation
     * @param  callable(mixed): string  $mName
     */
    private function formatMemberAttribute(array $violation, callable $mName): string
    {
        $label = (string) ($violation['attribute_label'] ?? $violation['attribute'] ?? 'attribute');
        $valueLabel = ! empty($violation['attribute_value_label'])
            ? ' "'.$violation['attribute_value_label'].'"'
            : '';

        $offending = $violation['offending_member_ids'] ?? [];
        if (is_array($offending) && count($offending) >= 2) {
            $names = array_map($mName, $offending);

            return $this->joinNames($names)." share {$label}{$valueLabel}";
        }

        if (isset($violation['distinct_count']) && is_numeric($violation['distinct_count'])) {
            return "Members have {$violation['distinct_count']} different {$label} values";
        }

        return "Members share the same {$label}{$valueLabel}";
    }

    /**
     * @param  list<string>  $names
     */
    private function joinNames(array $names): string
    {
        if ($names === []) {
            return '';
        }
        if (count($names) === 1) {
            return $names[0];
        }
        if (count($names) === 2) {
            return $names[0].' and '.$names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names).', and '.$last;
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function appendScoreSuffix(string $message, array $violation): string
    {
        if (! $this->shouldShowScoreSuffix($violation)) {
            return $message;
        }

        return $message.' (score: '.($violation['penalty'] ?? 0).')';
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    private function shouldShowScoreSuffix(array $violation): bool
    {
        $penalty = (int) ($violation['penalty'] ?? 0);
        if ($penalty <= 0) {
            return false;
        }

        $type = $violation['type'] ?? '';

        // Structural notes — informational only, never part of the penalty total.
        return ! in_array($type, ['team_size', 'group_size'], true);
    }
}
