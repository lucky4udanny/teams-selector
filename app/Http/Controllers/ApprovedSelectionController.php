<?php

namespace App\Http\Controllers;

use App\Models\ApprovedSelection;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

class ApprovedSelectionController extends Controller
{
    public function index(Request $request, Organization $organization): InertiaResponse
    {
        $this->authorize('view', $organization);

        $rows = $organization->approvedSelections()
            ->with('approver:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApprovedSelection $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'notes' => $s->notes,
                'occurred_at' => $s->occurred_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
                'total_penalty' => $s->snapshot['total_penalty'] ?? 0,
                'approved_by' => $s->approver?->name,
            ]);

        return Inertia::render('Selections/Index', [
            'organization' => $this->orgProps($request, $organization),
            'selections' => $rows,
        ]);
    }

    public function show(Request $request, Organization $organization, ApprovedSelection $selection): InertiaResponse
    {
        $this->authorize('view', $organization);
        if ($selection->organization_id !== $organization->id) {
            abort(404);
        }

        return Inertia::render('Selections/Show', [
            'organization' => $this->orgProps($request, $organization),
            'selection' => [
                'id' => $selection->id,
                'title' => $selection->title,
                'notes' => $selection->notes,
                'occurred_at' => $selection->occurred_at?->toIso8601String(),
                'created_at' => $selection->created_at?->toIso8601String(),
                'snapshot' => $selection->snapshot,
            ],
        ]);
    }

    public function print(Request $request, Organization $organization, ApprovedSelection $selection): InertiaResponse
    {
        $this->authorize('view', $organization);
        if ($selection->organization_id !== $organization->id) {
            abort(404);
        }

        return Inertia::render('Selections/Print', [
            'organization' => $this->orgProps($request, $organization),
            'selection' => [
                'id' => $selection->id,
                'title' => $selection->title,
                'snapshot' => $selection->snapshot,
            ],
        ]);
    }

    public function exportCsv(Request $request, Organization $organization, ApprovedSelection $selection): HttpResponse
    {
        $this->authorize('view', $organization);
        if ($selection->organization_id !== $organization->id) {
            abort(404);
        }

        $rows = $this->tabularRows($selection);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Group', 'Team', 'Member', 'Email']);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $filename = 'selection-'.$selection->id.'.csv';

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportXlsx(Request $request, Organization $organization, ApprovedSelection $selection)
    {
        $this->authorize('view', $organization);
        if ($selection->organization_id !== $organization->id) {
            abort(404);
        }

        $rows = $this->tabularRows($selection);
        $data = array_merge([['Group', 'Team', 'Member', 'Email']], $rows);

        $export = new class($data) implements FromArray
        {
            /**
             * @param  list<list<string>>  $data
             */
            public function __construct(private array $data) {}

            public function array(): array
            {
                return $this->data;
            }
        };

        return Excel::download($export, 'selection-'.$selection->id.'.xlsx');
    }

    /**
     * @return list<list<string>>
     */
    private function tabularRows(ApprovedSelection $selection): array
    {
        $snap = $selection->snapshot;
        $teams = $snap['teams'] ?? [];
        $groups = $snap['groups'] ?? [];
        $membersById = [];
        foreach ($snap['members'] ?? [] as $m) {
            $membersById[(int) $m['id']] = $m;
        }

        $out = [];
        foreach ($groups as $gi => $group) {
            $gnum = (string) ($gi + 1);
            foreach ($group['team_indices'] ?? [] as $ti) {
                $ti = (int) $ti;
                $tnum = (string) ($ti + 1);
                $memberIds = $teams[$ti]['member_ids'] ?? [];
                foreach ($memberIds as $mid) {
                    $mid = (int) $mid;
                    $m = $membersById[$mid] ?? ['name' => '#'.$mid, 'email' => ''];
                    $out[] = [$gnum, $tnum, (string) $m['name'], (string) ($m['email'] ?? '')];
                }
            }
        }

        if (count($out) === 0) {
            foreach ($teams as $ti => $team) {
                $tnum = (string) ($ti + 1);
                foreach ($team['member_ids'] ?? [] as $mid) {
                    $mid = (int) $mid;
                    $m = $membersById[$mid] ?? ['name' => '#'.$mid, 'email' => ''];
                    $out[] = ['', $tnum, (string) $m['name'], (string) ($m['email'] ?? '')];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function orgProps(Request $request, Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'role' => $organization->roleFor($request->user())?->value,
            'logo_url' => $organization->logoPublicUrl(),
            'brand_primary' => $organization->brand_primary,
            'brand_accent' => $organization->brand_accent,
        ];
    }
}
