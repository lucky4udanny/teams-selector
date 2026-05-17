<?php

namespace App\Http\Controllers;

use App\Enums\EventMemberStatus;
use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\Event;
use App\Models\Member;
use App\Models\MemberEventTypeSkill;
use App\Models\Organization;
use App\Models\TeamDraft;
use App\Services\EventPlanningConflictService;
use App\Services\TeamSolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamDraftController extends Controller
{
    use ProvidesOrganizationProps;

    public function __construct(
        private TeamSolverService $solver,
        private EventPlanningConflictService $conflicts,
    ) {}

    public function conflicts(Request $request, Organization $organization, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'conflicts' => $this->conflicts->analyze($event),
        ]);
    }

    public function generate(Request $request, Organization $organization, Event $event): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'include_pending' => ['nullable', 'boolean'],
            'iterations' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        $includePending = (bool) ($validated['include_pending'] ?? false);

        $memberQuery = $event->eventMembers()
            ->where('included', true)
            ->where(function ($q) use ($includePending): void {
                $q->where('status', EventMemberStatus::Accepted->value);
                if ($includePending) {
                    $q->orWhere('status', EventMemberStatus::Pending->value);
                }
            });

        $memberIds = $memberQuery->pluck('member_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $result = $this->solver->solve($event, $memberIds, (int) ($validated['iterations'] ?? 4000));

        $conflictList = $this->conflicts->analyze($event);

        $state = [
            'teams' => $result['teams'],
            'groups' => $result['groups'],
            'violations' => $result['violations'],
            'total_penalty' => $result['total_penalty'],
            'blocking_errors' => $result['blocking_errors'],
            'conflicts' => $conflictList,
            'member_ids' => $memberIds,
            'team_names' => [],
            'group_names' => [],
        ];

        $draft = TeamDraft::query()->create([
            'event_id' => $event->id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'] ?? null,
            'state' => $state,
            'is_final' => false,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'team_draft' => [
                    'id' => $draft->id,
                    'state' => $draft->state,
                ],
                'redirect_url' => route('organizations.events.team-drafts.show', [$organization, $event, $draft]),
            ]);
        }

        $label = $draft->name ? '"'.$draft->name.'"' : 'Draft #'.$draft->id;

        return redirect()
            ->route('organizations.events.team-drafts.show', [$organization, $event, $draft])
            ->with('status', "Team draft {$label} generated.");
    }

    public function show(Request $request, Organization $organization, Event $event, TeamDraft $teamDraft): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('TeamDrafts/Show', [
            'organization' => $this->organizationProps($request, $organization),
            'canUpdate' => $request->user()->can('update', $event) && ! $teamDraft->is_final,
            'canFinalize' => $request->user()->can('finalize', $event) && ! $teamDraft->is_final,
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_finalized' => $event->finalized_at !== null,
            ],
            'teamDraft' => [
                'id' => $teamDraft->id,
                'name' => $teamDraft->name,
                'state' => $teamDraft->state,
                'is_final' => $teamDraft->is_final,
                'created_at' => $teamDraft->created_at?->toIso8601String(),
            ],
            'conflicts' => $this->conflicts->analyze($event),
            'orgMembers' => (function () use ($organization, $event): \Illuminate\Support\Collection {
                $eventSkills = MemberEventTypeSkill::query()
                    ->where('event_type_id', $event->event_type_id)
                    ->pluck('skill_level', 'member_id')
                    ->all();

                return Member::query()
                    ->where('organization_id', $organization->id)
                    ->with('sector')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                    ->map(fn (Member $m) => [
                        'id' => $m->id,
                        'display_name' => $m->displayName(),
                        'first_name' => $m->first_name,
                        'last_name' => $m->last_name,
                        'gender' => $m->gender,
                        'email' => $m->email,
                        'phone' => $m->phone,
                        'company' => $m->company,
                        'sector' => $m->sector?->only(['id', 'name']),
                        'notes' => $m->notes,
                        'skill_level' => isset($eventSkills[$m->id]) ? (int) $eventSkills[$m->id] : null,
                    ]);
            })(),
        ]);
    }

    public function destroy(Request $request, Organization $organization, Event $event, TeamDraft $teamDraft): RedirectResponse
    {
        $this->authorize('update', $event);

        if ($teamDraft->is_final) {
            abort(422, 'Cannot delete a final draft.');
        }

        $teamDraft->delete();

        return redirect()
            ->route('organizations.events.show', [$organization, $event, 'tab' => 'drafts'])
            ->with('status', 'Draft deleted.');
    }

    public function finalize(Request $request, Organization $organization, Event $event, TeamDraft $teamDraft): RedirectResponse
    {
        $this->authorize('finalize', $event);

        if ($event->finalized_at) {
            return redirect()->back()->with('error', 'This event is already finalized.');
        }

        $state = is_array($teamDraft->state) ? $teamDraft->state : [];
        $blocking = $state['blocking_errors'] ?? [];
        if (is_array($blocking) && $blocking !== []) {
            return redirect()
                ->back()
                ->with('error', 'Cannot finalize: '.implode(' ', $blocking));
        }

        DB::transaction(function () use ($request, $event, $teamDraft): void {
            TeamDraft::query()
                ->where('event_id', $event->id)
                ->where('id', '!=', $teamDraft->id)
                ->update(['is_final' => false]);

            $teamDraft->update(['is_final' => true]);

            $event->update([
                'final_team_draft_id' => $teamDraft->id,
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->back()
            ->with('status', 'Teams finalized for this event.');
    }

    public function revertFinal(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->authorize('revertFinal', $event);

        $draftId = $event->final_team_draft_id;

        DB::transaction(function () use ($event, $draftId): void {
            $event->update([
                'final_team_draft_id' => null,
                'finalized_at' => null,
                'finalized_by' => null,
            ]);

            if ($draftId) {
                TeamDraft::query()->whereKey($draftId)->update(['is_final' => false]);
            }
        });

        return redirect()->back()->with('status', 'Final teams reverted. You can generate new drafts.');
    }

    public function updateTeamMembers(Request $request, Organization $organization, Event $event, TeamDraft $teamDraft): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'team_index' => ['required', 'integer', 'min:0'],
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $state = is_array($teamDraft->state) ? $teamDraft->state : [];
        $teams = $state['teams'] ?? [];
        $teamIndex = (int) $validated['team_index'];

        if (! isset($teams[$teamIndex])) {
            abort(422, "Team index {$teamIndex} does not exist in this draft.");
        }

        // Validate all member IDs belong to this org
        $orgMemberIds = Member::query()
            ->where('organization_id', $organization->id)
            ->pluck('id')
            ->all();

        $memberIds = array_values(array_unique(array_map('intval', $validated['member_ids'])));
        $invalid = array_diff($memberIds, $orgMemberIds);
        if ($invalid !== []) {
            abort(422, 'One or more members do not belong to this organisation.');
        }

        $state['teams'][$teamIndex]['member_ids'] = $memberIds;

        // Rebuild the global member_ids list from all teams
        $allMemberIds = [];
        foreach ($state['teams'] as $team) {
            foreach ($team['member_ids'] ?? [] as $mid) {
                $allMemberIds[] = (int) $mid;
            }
        }
        $state['member_ids'] = array_values(array_unique($allMemberIds));

        $teamDraft->update(['state' => $state]);

        if ($request->wantsJson()) {
            return response()->json(['teamDraft' => ['id' => $teamDraft->id, 'state' => $teamDraft->fresh()->state]]);
        }

        return redirect()->back()->with('status', 'Team members updated.');
    }

    public function updateNames(Request $request, Organization $organization, Event $event, TeamDraft $teamDraft): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'team_names' => ['nullable', 'array'],
            'team_names.*' => ['nullable', 'string', 'max:255'],
            'group_names' => ['nullable', 'array'],
            'group_names.*' => ['nullable', 'string', 'max:255'],
        ]);

        $state = $teamDraft->state ?? [];
        $state['team_names'] = $validated['team_names'] ?? ($state['team_names'] ?? []);
        $state['group_names'] = $validated['group_names'] ?? ($state['group_names'] ?? []);

        $teamDraft->update(['state' => $state]);

        if ($request->wantsJson()) {
            return response()->json(['teamDraft' => $teamDraft->fresh()]);
        }

        return redirect()->back()->with('status', 'Team and group names saved.');
    }
}
