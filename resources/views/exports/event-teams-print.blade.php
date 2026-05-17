<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Teams</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 9pt;
            color: #0f172a;
            padding: 0.6cm;
            line-height: 1.35;
        }

        /* ── Header ── */
        .page-header { margin-bottom: 10pt; padding-bottom: 6pt; border-bottom: 1pt solid #e2e8f0; }
        h1 { font-size: 13pt; font-weight: 700; color: #1e3a5f; }
        .meta { font-size: 8pt; color: #64748b; margin-top: 2pt; }

        /* ── Group section ── */
        .group { margin-bottom: 10pt; }
        .group-label {
            display: inline-block;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            background: #f1f5f9;
            border-radius: 3pt;
            padding: 2pt 6pt;
            margin-bottom: 5pt;
        }

        /* ── Team grid (2 teams per row) ── */
        .team-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5pt;
        }

        /* ── Team card ── */
        .team-card {
            border: 0.75pt solid #94a3b8;
            border-radius: 5pt;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .team-name {
            background: #e8eef4;
            padding: 3pt 7pt;
            font-size: 8pt;
            font-weight: 700;
            color: #1e3a5f;
            border-bottom: 0.75pt solid #94a3b8;
        }

        /* ── Member grid inside each team card ── */
        .member-grid {
            display: grid;
            gap: 3pt;
            padding: 4pt 5pt;
        }

        /* ── Individual member card ── */
        .member-card {
            background: #f8fafc;
            border: 0.5pt solid #e2e8f0;
            border-radius: 3pt;
            padding: 2pt 5pt;
        }

        .member-name { font-size: 8pt; font-weight: 600; color: #0f172a; }
        .member-detail { font-size: 7pt; color: #64748b; margin-top: 0.5pt; }

        /* ── Violations ── */
        .violations { margin-top: 4pt; padding-top: 3pt; border-top: 0.5pt solid #fde68a; }
        .violation { font-size: 6.5pt; color: #92400e; display: flex; gap: 3pt; margin-top: 1pt; }
        .violation-icon { flex-shrink: 0; }
        .group-violations { margin-bottom: 4pt; }
        .group-violation { font-size: 7pt; color: #92400e; display: flex; gap: 3pt; margin-bottom: 1pt; }

        @media print {
            body { padding: 0.3cm; }
            .group { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="page-header">
    <h1>{{ $event->name }}</h1>
    <p class="meta">
        {{ $event->event_date->format('M j, Y') }}
        @if($event->eventType) · {{ $event->eventType->name }} @endif
    </p>
</div>

@php
    function memberGridCols(int $count): int {
        return max(1, min(4, (int) ceil(sqrt($count))));
    }

    function formatViolationText(array $v, $members): string {
        $type = $v['type'] ?? '';
        if ($type === 'skill_leveling') {
            $avg    = isset($v['avg_skill']) ? round((float) $v['avg_skill'], 1) : '?';
            $min    = $v['min_avg'] ?? '?';
            $max    = $v['max_avg'] ?? '?';
            return "Skill levelling: avg {$avg} is outside {$min}–{$max}.";
        }
        if ($type === 'member_attribute') {
            $attr  = $v['attribute_label'] ?? ($v['attribute'] ?? 'attribute');
            $match = $v['attribute'] === null ? 'same' : ($v['attribute_value_label'] ?? '');
            $offIds = $v['offending_member_ids'] ?? [];
            $names = array_map(fn ($id) => $members[(int)$id]?->displayName() ?? "#$id", array_slice((array)$offIds, 0, 3));
            $nameStr = implode(' and ', $names);
            $val = $v['attribute_value_label'] ?? '';
            if ($nameStr && $val) return "{$nameStr} share {$attr}: {$val}.";
            if ($nameStr) return "{$nameStr} share the same {$attr}.";
            $distinct = $v['distinct_count'] ?? '';
            return $distinct ? "{$distinct} different {$attr} values on this team." : ($v['detail'] ?? '');
        }
        return $v['detail'] ?? '';
    }

    /**
     * Build a label showing only the parts whose columns are selected, in column order.
     * Returns an empty string when neither index nor name column is selected.
     */
    function resolveGroupLabel(int $gi, array $groupNames, array $columns): string {
        $letter = chr(65 + $gi);
        $name   = trim((string) ($groupNames[$gi] ?? ''));
        $parts  = [];
        foreach ($columns as $col) {
            if ($col === 'group_index') $parts[] = $letter;
            elseif ($col === 'group_name' && $name) $parts[] = $name;
        }
        if ($parts) return implode(' · ', $parts);
        // Fallback when only group_name is selected but name is empty
        if (in_array('group_name', $columns)) return $name ?: $letter;
        return '';
    }

    /**
     * Merge adjacent first_name + last_name entries (in either order) into one line.
     * Each entry must have a 'key' to identify it; other fields pass through unchanged.
     */
    function mergeAdjacentNames(array $fields): array {
        $result = [];
        for ($i = 0; $i < count($fields); $i++) {
            $curr = $fields[$i];
            $next = $fields[$i + 1] ?? null;
            $adjacent = $next && (
                ($curr['key'] === 'first_name' && $next['key'] === 'last_name') ||
                ($curr['key'] === 'last_name'  && $next['key'] === 'first_name')
            );
            if ($adjacent) {
                $result[] = ['type' => 'name', 'key' => 'full_name', 'value' => $curr['value'] . ' ' . $next['value']];
                $i++;
            } else {
                $result[] = $curr;
            }
        }
        return $result;
    }

    function resolveTeamLabel(int $ti, array $teamNames, array $columns): string {
        $num  = (string) ($ti + 1);
        $name = trim((string) ($teamNames[$ti] ?? ''));
        $parts = [];
        foreach ($columns as $col) {
            if ($col === 'team_index') $parts[] = $num;
            elseif ($col === 'team_name' && $name && $name !== $num) $parts[] = $name;
        }
        if ($parts) return implode(' · ', $parts);
        // Fallback when only team_name is selected but name equals num or is empty
        if (in_array('team_name', $columns)) return $name ?: $num;
        return '';
    }
@endphp

@if($hasGroups)
    @foreach($groups as $gi => $group)
        @php
            $groupLabel = resolveGroupLabel($gi, $groupNames, $columns);
            $gvs = $groupViolations[$gi] ?? [];
        @endphp
        <div class="group">
            @if($groupLabel)<div class="group-label">{{ $groupLabel }}</div>@endif
            @if($showViolations && count($gvs))
                <div class="group-violations">
                    @foreach($gvs as $v)
                        <div class="group-violation">
                            <span class="violation-icon">⚠</span>
                            <span>{{ formatViolationText($v, $members) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="team-grid">
                    @foreach($group['team_indices'] as $ti)
                    @php
                        $memberIds = $teams[$ti]['member_ids'] ?? [];
                        $cols      = memberGridCols(count($memberIds));
                        $teamLabel = resolveTeamLabel($ti, $teamNames, $columns);
                        $tvs       = $teamViolations[$ti] ?? [];
                    @endphp
                    <div class="team-card">
                        @if($teamLabel)<div class="team-name">{{ $teamLabel }}</div>@endif
                        <div class="member-grid" style="grid-template-columns: repeat({{ $cols }}, 1fr);">
                            @foreach($memberIds as $mid)
                                @php
                                    $m = $members[(int) $mid] ?? null;
                                    $memberFields = [];
                                    foreach ($columns as $col) {
                                        if ($col === 'display_name') {
                                            $memberFields[] = ['type' => 'name', 'key' => 'display_name', 'value' => $m ? $m->displayName() : "#$mid"];
                                        } elseif ($col === 'first_name' && $m?->first_name) {
                                            $memberFields[] = ['type' => 'name', 'key' => 'first_name', 'value' => $m->first_name];
                                        } elseif ($col === 'last_name' && $m?->last_name) {
                                            $memberFields[] = ['type' => 'name', 'key' => 'last_name', 'value' => $m->last_name];
                                        } elseif ($col === 'member_id') {
                                            $memberFields[] = ['type' => 'detail', 'key' => 'member_id', 'value' => "#$mid"];
                                        } elseif ($col === 'gender' && $m?->gender) {
                                            $memberFields[] = ['type' => 'detail', 'key' => 'gender', 'value' => $genderLabels[$m->gender] ?? $m->gender];
                                        } elseif ($col === 'email'   && $m?->email)   { $memberFields[] = ['type' => 'detail', 'key' => 'email',   'value' => $m->email]; }
                                        elseif ($col === 'phone'   && $m?->phone)   { $memberFields[] = ['type' => 'detail', 'key' => 'phone',   'value' => $m->phone]; }
                                        elseif ($col === 'company' && $m?->company) { $memberFields[] = ['type' => 'detail', 'key' => 'company', 'value' => $m->company]; }
                                        elseif ($col === 'sector'  && $m?->sector)  { $memberFields[] = ['type' => 'detail', 'key' => 'sector',  'value' => $m->sector->name]; }
                                        elseif ($col === 'skill') {
                                            $lvl = isset($skillByMember[(int)$mid]) ? (int)$skillByMember[(int)$mid] : 50;
                                            $memberFields[] = ['type' => 'detail', 'key' => 'skill', 'value' => "Skill: $lvl"];
                                        }
                                        elseif ($col === 'notes'   && $m?->notes)   { $memberFields[] = ['type' => 'detail', 'key' => 'notes',   'value' => $m->notes]; }
                                    }
                                    $memberFields = mergeAdjacentNames($memberFields);
                                @endphp
                                <div class="member-card">
                                    @foreach($memberFields as $field)
                                        @if($field['type'] === 'name')
                                            <div class="member-name">{{ $field['value'] }}</div>
                                        @else
                                            <div class="member-detail">{{ $field['value'] }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        @if($showViolations && count($tvs))
                            <div class="violations">
                                @foreach($tvs as $v)
                                    <div class="violation">
                                        <span class="violation-icon">⚠</span>
                                        <span>{{ formatViolationText($v, $members) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@else
    <div class="team-grid">
        @foreach($teams as $ti => $team)
            @php
                $memberIds = $team['member_ids'] ?? [];
                $cols      = memberGridCols(count($memberIds));
                $teamLabel = resolveTeamLabel($ti, $teamNames, $columns);
                $tvs       = $teamViolations[$ti] ?? [];
            @endphp
            <div class="team-card">
                @if($teamLabel)<div class="team-name">{{ $teamLabel }}</div>@endif
                <div class="member-grid" style="grid-template-columns: repeat({{ $cols }}, 1fr);">
                    @foreach($memberIds as $mid)
                        @php
                            $m = $members[(int) $mid] ?? null;
                            $memberFields = [];
                            foreach ($columns as $col) {
                                if ($col === 'display_name') {
                                    $memberFields[] = ['type' => 'name', 'key' => 'display_name', 'value' => $m ? $m->displayName() : "#$mid"];
                                } elseif ($col === 'first_name' && $m?->first_name) {
                                    $memberFields[] = ['type' => 'name', 'key' => 'first_name', 'value' => $m->first_name];
                                } elseif ($col === 'last_name' && $m?->last_name) {
                                    $memberFields[] = ['type' => 'name', 'key' => 'last_name', 'value' => $m->last_name];
                                } elseif ($col === 'member_id') {
                                    $memberFields[] = ['type' => 'detail', 'key' => 'member_id', 'value' => "#$mid"];
                                } elseif ($col === 'gender' && $m?->gender) {
                                    $memberFields[] = ['type' => 'detail', 'key' => 'gender', 'value' => $genderLabels[$m->gender] ?? $m->gender];
                                } elseif ($col === 'email'   && $m?->email)   { $memberFields[] = ['type' => 'detail', 'key' => 'email',   'value' => $m->email]; }
                                elseif ($col === 'phone'   && $m?->phone)   { $memberFields[] = ['type' => 'detail', 'key' => 'phone',   'value' => $m->phone]; }
                                elseif ($col === 'company' && $m?->company) { $memberFields[] = ['type' => 'detail', 'key' => 'company', 'value' => $m->company]; }
                                elseif ($col === 'sector'  && $m?->sector)  { $memberFields[] = ['type' => 'detail', 'key' => 'sector',  'value' => $m->sector->name]; }
                                elseif ($col === 'skill') {
                                    $lvl = isset($skillByMember[(int)$mid]) ? (int)$skillByMember[(int)$mid] : 50;
                                    $memberFields[] = ['type' => 'detail', 'key' => 'skill', 'value' => "Skill: $lvl"];
                                }
                                elseif ($col === 'notes'   && $m?->notes)   { $memberFields[] = ['type' => 'detail', 'key' => 'notes',   'value' => $m->notes]; }
                            }
                            $memberFields = mergeAdjacentNames($memberFields);
                        @endphp
                        <div class="member-card">
                            @foreach($memberFields as $field)
                                @if($field['type'] === 'name')
                                    <div class="member-name">{{ $field['value'] }}</div>
                                @else
                                    <div class="member-detail">{{ $field['value'] }}</div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @if($showViolations && count($tvs))
                    <div class="violations">
                        @foreach($tvs as $v)
                            <div class="violation">
                                <span class="violation-icon">⚠</span>
                                <span>{{ formatViolationText($v, $members) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

</body>
</html>
