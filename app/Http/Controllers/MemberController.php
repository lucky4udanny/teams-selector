<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use ProvidesOrganizationProps;

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $members = $organization->members()
            ->withTrashed()
            ->with(['sector', 'memberEventTypeSkills.eventType'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'first_name' => $m->first_name,
                'last_name' => $m->last_name,
                'name' => $m->displayName(),
                'email' => $m->email,
                'phone' => $m->phone,
                'company' => $m->company,
                'sector_id' => $m->sector_id,
                'sector' => $m->sector?->only(['id', 'name']),
                'notes' => $m->notes,
                'skills' => $m->memberEventTypeSkills->map(fn ($s) => [
                    'event_type_id' => $s->event_type_id,
                    'event_type_name' => $s->eventType->name,
                    'skill_level' => $s->skill_level,
                ]),
                'deleted_at' => $m->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Members/Index', [
            'organization' => $this->organizationProps($request, $organization),
            'members' => $members,
            'eventTypes' => $organization->eventTypes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'sectors' => $organization->sectors()->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('manageMembers', $organization),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $this->validatedMemberProfile($request, $organization);

        DB::transaction(function () use ($organization, $validated): void {
            $member = $organization->members()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'sector_id' => $validated['sector_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $this->syncMemberSkills($organization, $member, $validated['skills'] ?? []);
        });

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, Member $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        $validated = $this->validatedMemberProfile($request, $organization, $member->id);

        DB::transaction(function () use ($organization, $member, $validated): void {
            $member->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'sector_id' => $validated['sector_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $this->syncMemberSkills($organization, $member, $validated['skills'] ?? []);
        });

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, Member $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        $member->delete();

        return redirect()->back();
    }

    public function restore(Request $request, Organization $organization, int $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        $m = Member::onlyTrashed()->where('organization_id', $organization->id)->findOrFail($member);
        $m->restore();

        return redirect()->back();
    }

    public function import(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return redirect()->back()->withErrors(['file' => 'Could not read file.']);
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);

            return redirect()->back()->withErrors(['file' => 'Empty CSV.']);
        }

        $normalize = fn (string $s): string => strtolower(trim($s));
        $header = array_map($normalize, $header);

        $idx = static function (array $header, string $name): ?int {
            $i = array_search($name, $header, true);

            return $i === false ? null : $i;
        };

        $firstIdx = $idx($header, 'first_name');
        $lastIdx = $idx($header, 'last_name');

        if ($firstIdx === null && $lastIdx === null) {
            fclose($fh);

            return redirect()->back()->withErrors(['file' => 'CSV must include first_name and/or last_name columns.']);
        }

        $emailIdx = $idx($header, 'email');
        $phoneIdx = $idx($header, 'phone');
        $companyIdx = $idx($header, 'company');
        $sectorIdx = $idx($header, 'sector');
        $notesIdx = $idx($header, 'notes');

        $sectorsByName = $organization->sectors()
            ->get()
            ->mapWithKeys(fn ($s) => [mb_strtolower($s->name) => $s->id]);

        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $first = $firstIdx !== null ? trim((string) ($row[$firstIdx] ?? '')) : '';
            $last = $lastIdx !== null ? trim((string) ($row[$lastIdx] ?? '')) : '';
            if ($first === '' && $last === '') {
                continue;
            }

            $sectorId = null;
            if ($sectorIdx !== null) {
                $raw = trim((string) ($row[$sectorIdx] ?? ''));
                if ($raw !== '') {
                    $sectorId = $sectorsByName[mb_strtolower($raw)] ?? null;
                }
            }

            $email = $emailIdx !== null ? trim((string) ($row[$emailIdx] ?? '')) : '';
            $organization->members()->create([
                'first_name' => $first !== '' ? $first : ($last !== '' ? $last : 'Member'),
                'last_name' => $first !== '' ? ($last !== '' ? $last : null) : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phoneIdx !== null && trim((string) ($row[$phoneIdx] ?? '')) !== '' ? trim((string) $row[$phoneIdx]) : null,
                'company' => $companyIdx !== null && trim((string) ($row[$companyIdx] ?? '')) !== '' ? trim((string) $row[$companyIdx]) : null,
                'sector_id' => $sectorId,
                'notes' => $notesIdx !== null && trim((string) ($row[$notesIdx] ?? '')) !== '' ? trim((string) $row[$notesIdx]) : null,
            ]);
            $count++;
        }
        fclose($fh);

        return redirect()->back()->with('status', 'Imported '.$count.' members.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMemberProfile(Request $request, Organization $organization, ?int $existingMemberId = null): array
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'sector_id' => ['nullable', 'integer', Rule::exists('sectors', 'id')->where('organization_id', $organization->id)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*.event_type_id' => ['required', 'integer', Rule::exists('event_types', 'id')->where('organization_id', $organization->id)],
            'skills.*.skill_level' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        return $validated;
    }

    /**
     * @param  list<array{event_type_id: int, skill_level: int}>  $skills
     */
    private function syncMemberSkills(Organization $organization, Member $member, array $skills): void
    {
        $member->memberEventTypeSkills()->delete();
        foreach ($skills as $row) {
            if (! isset($row['event_type_id'], $row['skill_level'])) {
                continue;
            }
            if (! $organization->eventTypes()->whereKey($row['event_type_id'])->exists()) {
                continue;
            }
            $member->memberEventTypeSkills()->create([
                'event_type_id' => (int) $row['event_type_id'],
                'skill_level' => (int) $row['skill_level'],
            ]);
        }
    }
}
