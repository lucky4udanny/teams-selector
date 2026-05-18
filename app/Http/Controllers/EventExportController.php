<?php

namespace App\Http\Controllers;

use App\Exports\EventTeamsExport;
use App\Models\Event;
use App\Models\Member;
use App\Models\MemberEventTypeSkill;
use App\Models\Organization;
use App\Models\TeamDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventExportController extends Controller
{
    private const ALL_COLUMNS = [
        'team_index',
        'team_name',
        'group_index',
        'group_name',
        'member_id',
        'display_name',
        'first_name',
        'last_name',
        'gender',
        'email',
        'phone',
        'company',
        'sector',
        'skill',
        'notes',
    ];

    private const GENDER_LABELS = [
        'male'             => 'Male',
        'female'           => 'Female',
        'non_binary'       => 'Non-binary',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    public function print(Request $request, Organization $organization, Event $event): Response
    {
        $this->authorize('view', $event);

        $draft = $this->resolveDraft($request, $event);
        $columns = $this->parseColumns($request);
        $showViolations = $request->query('violations', '1') !== '0';

        $state = is_array($draft->state) ? $draft->state : [];
        $teams = $state['teams'] ?? [];
        $groups = $state['groups'] ?? [];
        $teamNames = $state['team_names'] ?? [];
        $groupNames = $state['group_names'] ?? [];
        $violations = is_array($state['violations'] ?? null) ? $state['violations'] : [];

        $memberIds = array_values(array_unique(
            array_merge([], ...array_map(fn ($t) => $t['member_ids'] ?? [], $teams))
        ));

        $members = Member::query()
            ->where('organization_id', $event->organization_id)
            ->with('sector')
            ->get()
            ->keyBy('id');

        $skillByMember = [];
        if (in_array('skill', $columns, true) && $event->event_type_id) {
            $skillByMember = MemberEventTypeSkill::query()
                ->where('event_type_id', $event->event_type_id)
                ->whereIn('member_id', $memberIds)
                ->pluck('skill_level', 'member_id')
                ->all();
        }

        // Index violations by team and group for easy lookup in the template
        $teamViolations = [];
        $groupViolations = [];
        foreach ($violations as $v) {
            if (isset($v['team_index']) && is_int($v['team_index'])) {
                $teamViolations[$v['team_index']][] = $v;
            } elseif (isset($v['group_index']) && is_int($v['group_index'])) {
                $groupViolations[$v['group_index']][] = $v;
            }
        }

        $avgSkill = in_array('skill', $columns, true)
            ? $this->computeAvgSkill($teams, $groups, $skillByMember)
            : ['team' => [], 'group' => []];

        return response()->view('exports.event-teams-print', [
            'event'           => $event->loadMissing('eventType'),
            'teams'           => $teams,
            'groups'          => $groups,
            'teamNames'       => $teamNames,
            'groupNames'      => $groupNames,
            'members'         => $members,
            'columns'         => $columns,
            'hasGroups'       => ! empty($groups),
            'skillByMember'   => $skillByMember,
            'teamAvgSkill'    => $avgSkill['team'],
            'groupAvgSkill'   => $avgSkill['group'],
            'genderLabels'    => self::GENDER_LABELS,
            'showViolations'  => $showViolations,
            'teamViolations'  => $teamViolations,
            'groupViolations' => $groupViolations,
        ]);
    }

    public function exportCsv(Request $request, Organization $organization, Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $draft = $this->resolveDraft($request, $event);
        $columns = $this->parseColumns($request);
        $table = $this->buildTable($event, $draft, $columns);

        $export = new EventTeamsExport($table['headings'], $table['rows']);
        $filename = 'event-'.$event->id.'-teams.csv';

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportXlsx(Request $request, Organization $organization, Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $draft = $this->resolveDraft($request, $event);
        $columns = $this->parseColumns($request);
        $table = $this->buildTable($event, $draft, $columns);

        $export = new EventTeamsExport($table['headings'], $table['rows']);
        $filename = 'event-'.$event->id.'-teams.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Compute rounded average skill per team and per group.
     * Members without a recorded skill default to 50 (matching the solver).
     *
     * @param  array<int, array{member_ids: list<int>}>  $teams
     * @param  array<int, array{team_indices: list<int>}>  $groups
     * @param  array<int, int>  $skillByMember
     * @return array{team: array<int, int>, group: array<int, int>}
     */
    private function computeAvgSkill(array $teams, array $groups, array $skillByMember): array
    {
        $teamAvg = [];
        foreach ($teams as $ti => $team) {
            $ids = $team['member_ids'] ?? [];
            if ($ids === []) {
                continue;
            }
            $sum = array_sum(array_map(fn ($mid) => $skillByMember[$mid] ?? 50, $ids));
            $teamAvg[$ti] = (int) round($sum / count($ids));
        }

        $groupAvg = [];
        foreach ($groups as $gi => $group) {
            $allIds = array_merge([], ...array_map(
                fn ($ti) => ($teams[$ti] ?? [])['member_ids'] ?? [],
                $group['team_indices'] ?? [],
            ));
            if ($allIds === []) {
                continue;
            }
            $sum = array_sum(array_map(fn ($mid) => $skillByMember[$mid] ?? 50, $allIds));
            $groupAvg[$gi] = (int) round($sum / count($allIds));
        }

        return ['team' => $teamAvg, 'group' => $groupAvg];
    }

    private function resolveDraft(Request $request, Event $event): TeamDraft
    {
        $draftId = (int) $request->query('draft_id', 0);

        if ($draftId > 0) {
            $draft = TeamDraft::query()
                ->where('event_id', $event->id)
                ->find($draftId);

            if (! $draft instanceof TeamDraft) {
                abort(404, 'Draft not found.');
            }

            return $draft;
        }

        return $this->finalDraftOrAbort($event);
    }

    private function finalDraftOrAbort(Event $event): TeamDraft
    {
        if (! $event->finalized_at || ! $event->final_team_draft_id) {
            abort(422, 'Event must be finalized to export.');
        }

        $draft = TeamDraft::query()->find($event->final_team_draft_id);
        if (! $draft instanceof TeamDraft) {
            abort(422, 'Final draft is missing.');
        }

        return $draft;
    }

    /**
     * @return list<string>
     */
    private function parseColumns(Request $request): array
    {
        $raw = (string) $request->query('columns', '');
        if (trim($raw) === '') {
            return self::ALL_COLUMNS;
        }

        $requested = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ($requested as $c) {
            if (in_array($c, self::ALL_COLUMNS, true)) {
                $out[] = $c;
            }
        }

        return $out !== [] ? $out : self::ALL_COLUMNS;
    }

    /**
     * @param  list<string>  $columns
     * @return array{headings: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function buildTable(Event $event, TeamDraft $draft, array $columns): array
    {
        $state = is_array($draft->state) ? $draft->state : [];
        $teams = $state['teams'] ?? [];
        $groups = $state['groups'] ?? [];
        $teamNames = $state['team_names'] ?? [];
        $groupNames = $state['group_names'] ?? [];

        $members = Member::query()
            ->where('organization_id', $event->organization_id)
            ->with('sector')
            ->get()
            ->keyBy('id');

        $allMemberIds = array_values(array_unique(
            array_merge([], ...array_map(fn ($t) => $t['member_ids'] ?? [], $teams))
        ));

        $skillByMember = [];
        if (in_array('skill', $columns, true) && $event->event_type_id) {
            $skillByMember = MemberEventTypeSkill::query()
                ->where('event_type_id', $event->event_type_id)
                ->whereIn('member_id', $allMemberIds)
                ->pluck('skill_level', 'member_id')
                ->all();
        }

        $groupMetaForTeam = [];
        foreach ($groups as $gi => $group) {
            foreach (($group['team_indices'] ?? []) as $ti) {
                $groupMetaForTeam[(int) $ti] = [
                    'group_index' => $gi,
                    'group_name' => $groupNames[$gi] ?? ('Group '.($gi + 1)),
                ];
            }
        }

        $includeAvgSkill = in_array('skill', $columns, true);
        $avgSkill = $includeAvgSkill
            ? $this->computeAvgSkill($teams, $groups, $skillByMember)
            : ['team' => [], 'group' => []];

        $headings = array_map(fn (string $c) => match ($c) {
            'team_index' => 'Team #',
            'team_name' => 'Team',
            'group_index' => 'Group #',
            'group_name' => 'Group',
            'member_id' => 'Member ID',
            'display_name' => 'Name',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'gender' => 'Gender',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'sector' => 'Sector',
            'skill' => 'Skill',
            'notes' => 'Notes',
            default => $c,
        }, $columns);

        if ($includeAvgSkill) {
            $headings[] = 'Team avg skill';
            if (! empty($groups)) {
                $headings[] = 'Group avg skill';
            }
        }

        $rows = [];
        foreach ($teams as $ti => $team) {
            $tname = $teamNames[$ti] ?? ('Team '.($ti + 1));
            $ginfo = $groupMetaForTeam[$ti] ?? ['group_index' => null, 'group_name' => ''];
            foreach (($team['member_ids'] ?? []) as $mid) {
                $mid = (int) $mid;
                $m = $members->get($mid);
                $row = [];
                foreach ($columns as $col) {
                    $row[] = match ($col) {
                        'team_index' => $ti + 1,
                        'team_name' => $tname,
                        'group_index' => $ginfo['group_index'] !== null ? $ginfo['group_index'] + 1 : '',
                        'group_name' => $ginfo['group_name'],
                        'member_id' => $mid,
                        'display_name' => $m ? $m->displayName() : "#$mid",
                        'first_name' => $m?->first_name,
                        'last_name' => $m?->last_name,
                        'gender' => self::GENDER_LABELS[$m?->gender ?? ''] ?? $m?->gender,
                        'email' => $m?->email,
                        'phone' => $m?->phone,
                        'company' => $m?->company,
                        'sector' => $m?->sector?->name,
                        'skill' => isset($skillByMember[$mid]) ? (int) $skillByMember[$mid] : 50,
                        'notes' => $m?->notes,
                        default => null,
                    };
                }
                if ($includeAvgSkill) {
                    $row[] = $avgSkill['team'][$ti] ?? null;
                    if (! empty($groups)) {
                        $gi = $ginfo['group_index'];
                        $row[] = $gi !== null ? ($avgSkill['group'][$gi] ?? null) : null;
                    }
                }

                $rows[] = $row;
            }
        }

        return ['headings' => $headings, 'rows' => $rows];
    }
}
