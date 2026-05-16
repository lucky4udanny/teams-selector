<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\Member;
use App\Models\Organization;
use App\Services\MemberCsvImportParser;
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

    public function import(Request $request, Organization $organization, MemberCsvImportParser $parser): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $uploaded = $request->file('file');
        if ($uploaded === null) {
            return redirect()->back()->withErrors(['file' => 'Could not read file.']);
        }

        $path = $uploaded->getRealPath();
        if ($path === false) {
            return redirect()->back()->withErrors(['file' => 'Could not read file.']);
        }

        try {
            $parsed = $parser->parseFile($path, $uploaded->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['file' => $e->getMessage()]);
        }

        $indices = $parsed['indices'];
        $sectorsByName = $organization->sectors()
            ->get()
            ->mapWithKeys(fn ($s) => [mb_strtolower($s->name) => $s->id]);

        $count = 0;
        $skipped = 0;

        foreach ($parsed['rows'] as $row) {
            $first = $indices['first'] !== null ? trim((string) ($row[$indices['first']] ?? '')) : '';
            $last = $indices['last'] !== null ? trim((string) ($row[$indices['last']] ?? '')) : '';
            if ($first === '' && $last === '') {
                $skipped++;

                continue;
            }

            $sectorId = null;
            if ($indices['sector'] !== null) {
                $raw = trim((string) ($row[$indices['sector']] ?? ''));
                if ($raw !== '') {
                    $sectorId = $sectorsByName[mb_strtolower($raw)] ?? null;
                }
            }

            $email = $indices['email'] !== null ? trim((string) ($row[$indices['email']] ?? '')) : '';

            $organization->members()->create([
                'first_name' => $first !== '' ? $first : ($last !== '' ? $last : 'Member'),
                'last_name' => $first !== '' ? ($last !== '' ? $last : null) : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $indices['phone'] !== null && trim((string) ($row[$indices['phone']] ?? '')) !== ''
                    ? trim((string) $row[$indices['phone']])
                    : null,
                'company' => $indices['company'] !== null && trim((string) ($row[$indices['company']] ?? '')) !== ''
                    ? trim((string) $row[$indices['company']])
                    : null,
                'sector_id' => $sectorId,
                'notes' => $indices['notes'] !== null && trim((string) ($row[$indices['notes']] ?? '')) !== ''
                    ? trim((string) $row[$indices['notes']])
                    : null,
            ]);
            $count++;
        }

        if ($count === 0) {
            return redirect()->back()->withErrors([
                'file' => 'No member rows found. Each row needs at least a first or last name.',
            ]);
        }

        $message = 'Imported '.$count.' member'.($count === 1 ? '' : 's').'.';
        if ($skipped > 0) {
            $message .= ' Skipped '.$skipped.' empty row'.($skipped === 1 ? '' : 's').'.';
        }

        return redirect()->back()->with('status', $message);
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
