<?php

namespace App\Http\Controllers;

use App\Exports\EventTeamsExport;
use App\Models\Event;
use App\Models\Member;
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
        'email',
        'phone',
        'company',
        'sector',
        'notes',
    ];

    public function print(Request $request, Organization $organization, Event $event): Response
    {
        $this->authorize('view', $event);

        $draft = $this->finalDraftOrAbort($event);
        $columns = $this->parseColumns($request);

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

        $teamLabels = [];
        foreach (array_keys($teams) as $ti) {
            $num = (string) ($ti + 1);
            $name = trim((string) ($teamNames[$ti] ?? ''));
            $teamLabels[$ti] = ($name && $name !== $num) ? "$num · $name" : ($name ?: $num);
        }

        $groupLabels = [];
        foreach (array_keys($groups) as $gi) {
            $letter = chr(65 + $gi);
            $name = trim((string) ($groupNames[$gi] ?? ''));
            $groupLabels[$gi] = ($name && $name !== $letter) ? "$letter · $name" : ($name ?: $letter);
        }

        return response()->view('exports.event-teams-print', [
            'event' => $event->loadMissing('eventType'),
            'teams' => $teams,
            'groups' => $groups,
            'teamNames' => $teamNames,
            'groupNames' => $groupNames,
            'members' => $members,
            'columns' => $columns,
            'hasGroups' => ! empty($groups),
        ]);
    }

    public function exportCsv(Request $request, Organization $organization, Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $draft = $this->finalDraftOrAbort($event);
        $columns = $this->parseColumns($request);
        $table = $this->buildTable($event, $draft, $columns);

        $export = new EventTeamsExport($table['headings'], $table['rows']);
        $filename = 'event-'.$event->id.'-teams.csv';

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportXlsx(Request $request, Organization $organization, Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $draft = $this->finalDraftOrAbort($event);
        $columns = $this->parseColumns($request);
        $table = $this->buildTable($event, $draft, $columns);

        $export = new EventTeamsExport($table['headings'], $table['rows']);
        $filename = 'event-'.$event->id.'-teams.xlsx';

        return Excel::download($export, $filename);
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

        $groupMetaForTeam = [];
        foreach ($groups as $gi => $group) {
            foreach (($group['team_indices'] ?? []) as $ti) {
                $groupMetaForTeam[(int) $ti] = [
                    'group_index' => $gi,
                    'group_name' => $groupNames[$gi] ?? ('Group '.($gi + 1)),
                ];
            }
        }

        $headings = array_map(fn (string $c) => match ($c) {
            'team_index' => 'Team #',
            'team_name' => 'Team',
            'group_index' => 'Group #',
            'group_name' => 'Group',
            'member_id' => 'Member ID',
            'display_name' => 'Name',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'sector' => 'Sector',
            'notes' => 'Notes',
            default => $c,
        }, $columns);

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
                        'email' => $m?->email,
                        'phone' => $m?->phone,
                        'company' => $m?->company,
                        'sector' => $m?->sector?->name,
                        'notes' => $m?->notes,
                        default => null,
                    };
                }
                $rows[] = $row;
            }
        }

        return ['headings' => $headings, 'rows' => $rows];
    }
}
