<?php

namespace App\Http\Controllers;

use App\Exports\EventTeamsExport;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Services\RosterFilter;
use App\Services\RosterFilterCriteria;
use App\Services\RosterSorter;
use App\Services\ViolationFormatter;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventRosterExportController extends Controller
{
    private const GENDER_LABELS = [
        'male' => 'Male',
        'female' => 'Female',
        'non_binary' => 'Non-binary',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    private const STATUS_LABELS = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
    ];

    public function __construct(
        private EventMemberController $eventMemberController,
        private ViolationFormatter $violationFormatter,
    ) {}

    public function exportCsv(Request $request, Organization $organization, Event $event): BinaryFileResponse
    {
        $this->authorize('view', $event);

        $filters = RosterFilterCriteria::fromRequest($request);
        $sorter = RosterSorter::fromRequest($request);
        $table = $this->buildTable($event, $filters, $sorter);

        $export = new EventTeamsExport($table['headings'], $table['rows']);
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($event->name)) ?: 'event';
        $filename = trim($slug, '-').'-roster.csv';

        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function buildTable(Event $event, RosterFilterCriteria $filters, RosterSorter $sorter): array
    {
        $payload = $this->eventMemberController->rosterPayloadPublic($event);
        $roster = $payload['roster'];

        $members = Member::query()
            ->where('organization_id', $event->organization_id)
            ->with('sector')
            ->get()
            ->keyBy('id');

        $filtered = [];
        foreach ($roster as $row) {
            $memberId = (int) ($row['member_id'] ?? 0);
            /** @var Member|null $orgMember */
            $orgMember = $members->get($memberId);
            if (! RosterFilter::matches($row, $orgMember, $filters)) {
                continue;
            }
            $filtered[] = $row;
        }

        $sorted = $sorter->sort($filtered);

        $headings = [
            'Member ID',
            'Name',
            'First name',
            'Last name',
            'Email',
            'Phone',
            'Company',
            'Sector',
            'Gender',
            'Skill',
            'Included',
            'Invited',
            'Status',
            'Notes',
        ];

        $rows = [];
        foreach ($sorted as $row) {
            $memberId = (int) ($row['member_id'] ?? 0);
            /** @var Member|null $orgMember */
            $orgMember = $members->get($memberId);
            $genderKey = $orgMember?->gender ?? '';

            $rows[] = $this->violationFormatter->sanitizeSpreadsheetRow([
                $memberId,
                (string) ($row['display_name'] ?? ''),
                (string) ($row['first_name'] ?? ''),
                (string) ($row['last_name'] ?? ''),
                (string) ($row['email'] ?? ''),
                $orgMember?->phone,
                $orgMember?->company,
                (string) ($row['sector']['name'] ?? $orgMember?->sector?->name ?? ''),
                self::GENDER_LABELS[$genderKey] ?? $genderKey,
                isset($row['skill_level']) ? (int) $row['skill_level'] : '',
                ($row['included'] ?? false) ? 'Yes' : 'No',
                ($row['invited'] ?? false) ? 'Yes' : 'No',
                self::STATUS_LABELS[$row['status'] ?? ''] ?? ($row['status'] ?? ''),
                (string) ($row['notes'] ?? ''),
            ]);
        }

        return ['headings' => $headings, 'rows' => $rows];
    }
}
