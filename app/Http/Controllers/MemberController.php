<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $members = $organization->members()
            ->withTrashed()
            ->orderBy('name')
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'notes' => $m->notes,
                'deleted_at' => $m->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Members/Index', [
            'organization' => $this->orgProps($request, $organization),
            'members' => $members,
            'canManage' => Gate::allows('manageMembers', $organization),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization->members()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, Member $member): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        if ($member->organization_id !== $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member->update($validated);

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
        $nameIdx = array_search('name', $header, true);
        if ($nameIdx === false) {
            fclose($fh);

            return redirect()->back()->withErrors(['file' => 'CSV must include a "name" column.']);
        }

        $emailIdx = array_search('email', $header, true);
        $notesIdx = array_search('notes', $header, true);

        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $name = trim((string) ($row[$nameIdx] ?? ''));
            if ($name === '') {
                continue;
            }
            $email = $emailIdx !== false ? trim((string) ($row[$emailIdx] ?? '')) : null;
            $notes = $notesIdx !== false ? trim((string) ($row[$notesIdx] ?? '')) : null;

            $organization->members()->create([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            $count++;
        }
        fclose($fh);

        return redirect()->back()->with('status', 'Imported '.$count.' members.');
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
