<?php

namespace App\Http\Controllers;

use App\Models\ApprovedSelection;
use App\Models\Organization;
use App\Models\SelectionDraft;
use App\Services\TeamSolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelectionDraftController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('manageDrafts', $organization);

        $drafts = $organization->selectionDrafts()
            ->where('status', 'draft')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SelectionDraft $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'updated_at' => $d->updated_at?->toIso8601String(),
                'total_penalty' => $d->state['total_penalty'] ?? null,
            ]);

        return Inertia::render('Drafts/Index', [
            'organization' => $this->orgProps($request, $organization),
            'drafts' => $drafts,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageDrafts', $organization);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $draft = $organization->selectionDrafts()->create([
            'created_by' => $request->user()->id,
            'name' => $validated['name'] ?? null,
            'status' => 'draft',
            'state' => [
                'teams' => [],
                'groups' => [],
                'violations' => [],
                'total_penalty' => 0,
                'blocking_errors' => [],
                'members_snapshot' => [],
            ],
        ]);

        return redirect()->route('organizations.drafts.show', [$organization, $draft]);
    }

    public function show(Request $request, Organization $organization, SelectionDraft $draft): Response|RedirectResponse
    {
        $this->authorize('manageDrafts', $organization);
        if ($draft->organization_id !== $organization->id) {
            abort(404);
        }

        if ($draft->status !== 'draft') {
            $approved = ApprovedSelection::query()
                ->where('organization_id', $organization->id)
                ->where('selection_draft_id', $draft->id)
                ->first();

            if ($approved) {
                return redirect()->route('organizations.selections.show', [$organization, $approved]);
            }
        }

        $memberIds = $organization->members()->pluck('id')->all();
        $members = $organization->members()->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Drafts/Show', [
            'organization' => $this->orgProps($request, $organization),
            'draft' => [
                'id' => $draft->id,
                'name' => $draft->name,
                'state' => $draft->state,
            ],
            'memberIds' => $memberIds,
            'members' => $members,
            'canApprove' => $request->user()->can('approveSelections', $organization),
        ]);
    }

    public function generate(Request $request, Organization $organization, SelectionDraft $draft, TeamSolverService $solver): RedirectResponse
    {
        $this->authorize('manageDrafts', $organization);
        if ($draft->organization_id !== $organization->id) {
            abort(404);
        }

        $memberIds = $organization->members()->pluck('id')->all();

        $result = $solver->solve($organization, $memberIds);

        $membersSnapshot = $organization->members()
            ->whereIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($m) => $m->toArray())
            ->values()
            ->all();

        $draft->state = [
            'teams' => $result['teams'],
            'groups' => $result['groups'],
            'violations' => $result['violations'],
            'total_penalty' => $result['total_penalty'],
            'blocking_errors' => $result['blocking_errors'],
            'members_snapshot' => $membersSnapshot,
        ];
        $draft->save();

        return redirect()->back();
    }

    public function approve(Request $request, Organization $organization, SelectionDraft $draft): RedirectResponse
    {
        $this->authorize('approveSelections', $organization);
        if ($draft->organization_id !== $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $state = $draft->state;
        if (! empty($state['blocking_errors'])) {
            return redirect()->back()->withErrors(['approve' => 'Fix blocking errors before approving.']);
        }

        $snapshot = [
            'teams' => $state['teams'] ?? [],
            'groups' => $state['groups'] ?? [],
            'violations' => $state['violations'] ?? [],
            'total_penalty' => $state['total_penalty'] ?? 0,
            'members' => $state['members_snapshot'] ?? [],
            'branding' => $organization->brandingPayload(),
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? null,
        ];

        $approved = ApprovedSelection::query()->create([
            'organization_id' => $organization->id,
            'selection_draft_id' => $draft->id,
            'approved_by' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'occurred_at' => isset($validated['occurred_at']) ? $validated['occurred_at'] : null,
            'snapshot' => $snapshot,
        ]);

        $draft->status = 'approved';
        $draft->save();

        return redirect()->route('organizations.selections.show', [$organization, $approved]);
    }

    public function destroy(Request $request, Organization $organization, SelectionDraft $draft): RedirectResponse
    {
        $this->authorize('manageDrafts', $organization);
        if ($draft->organization_id !== $organization->id) {
            abort(404);
        }

        $draft->delete();

        return redirect()->route('organizations.drafts.index', $organization);
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
