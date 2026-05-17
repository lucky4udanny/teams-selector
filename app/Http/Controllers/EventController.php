<?php

namespace App\Http\Controllers;

use App\Enums\EventMemberStatus;
use App\Enums\RuleScope;
use App\Enums\RuleType;
use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Member;
use App\Models\MemberEventTypeSkill;
use App\Models\Organization;
use App\Models\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use ProvidesOrganizationProps;

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $events = $organization->events()
            ->with('eventType')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Event $e) {
                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'event_date' => $e->event_date->format('Y-m-d'),
                    'event_type_name' => $e->eventType->name,
                    'finalized' => $e->finalized_at !== null,
                    'rsvp_counts' => $e->rsvpCounts(),
                ];
            });

        return Inertia::render('Events/Index', [
            'organization' => $this->organizationProps($request, $organization),
            'events' => $events,
            'eventTypes' => $organization->eventTypes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('manageMembers', $organization),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $this->validatedEventPayload($request, $organization, null);
        $previousIds = $validated['previous_event_ids'] ?? [];

        $event = DB::transaction(function () use ($organization, $validated, $previousIds): Event {
            $event = $organization->events()->create([
                'event_type_id' => $validated['event_type_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'event_date' => $validated['event_date'],
                'uses_groups' => (bool) ($validated['uses_groups'] ?? false),
            ]);

            $event->previousEvents()->sync($previousIds);

            return $event;
        });

        return redirect()
            ->route('organizations.events.show', [$organization, $event])
            ->with('status', 'Event "'.$validated['name'].'" was created.');
    }

    public function show(Request $request, Organization $organization, Event $event): Response|RedirectResponse
    {
        $this->authorize('view', $event);

        $tab = $request->query('tab', 'details');
        if ($tab === 'final') {
            return redirect()->to(
                route('organizations.events.show', [
                    'organization' => $organization->slug,
                    'event' => $event->id,
                ]).'?tab=drafts'
            );
        }
        if (! in_array($tab, ['details', 'roster', 'rules', 'drafts'], true)) {
            $tab = 'details';
        }

        $event->load(['eventType', 'finalTeamDraft', 'previousEvents']);

        $eventSkills = MemberEventTypeSkill::query()
            ->where('event_type_id', $event->event_type_id)
            ->pluck('skill_level', 'member_id')
            ->all();

        $membersPick = Member::query()
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
                'email' => $m->email,
                'phone' => $m->phone,
                'company' => $m->company,
                'sector' => $m->sector?->only(['id', 'name']),
                'notes' => $m->notes,
                'skill_level' => isset($eventSkills[$m->id]) ? (int) $eventSkills[$m->id] : null,
            ]);

        $payload = [
            'organization' => $this->organizationProps($request, $organization),
            'tab' => $tab,
            'canManage' => $request->user()->can('update', $event),
            'canFinalize' => $request->user()->can('finalize', $event),
            'canRevertFinal' => $request->user()->can('revertFinal', $event),
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'event_date' => $event->event_date->format('Y-m-d'),
                'uses_groups' => $event->uses_groups,
                'finalized_at' => $event->finalized_at?->toIso8601String(),
                'is_finalized' => $event->finalized_at !== null,
                'event_type' => $event->eventType->only(['id', 'name']),
                'previous_event_ids' => $event->previousEvents->pluck('id')->values()->all(),
                'rsvp_counts' => $event->rsvpCounts(),
                'final_team_draft_id' => $event->final_team_draft_id,
            ],
            'orgMembers' => $membersPick,
            'ruleTypes' => array_map(fn (RuleType $t) => $t->value, RuleType::cases()),
            'ruleScopes' => array_map(fn (RuleScope $s) => $s->value, RuleScope::cases()),
            'eventTypes' => $organization->eventTypes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'finalizedEvents' => $organization->events()
                ->whereNotNull('finalized_at')
                ->where('id', '!=', $event->id)
                ->orderByDesc('event_date')
                ->get(['id', 'name', 'event_date'])
                ->map(fn (Event $e) => [
                    'id' => $e->id,
                    'label' => $e->name.' ('.$e->event_date->format('Y-m-d').')',
                ]),
            'copySourceEvents' => $organization->events()
                ->where('id', '!=', $event->id)
                ->orderByDesc('event_date')
                ->get(['id', 'name'])
                ->map(fn (Event $e) => ['id' => $e->id, 'label' => $e->name]),
        ];

        if ($tab === 'roster') {
            $rosterData = app(EventMemberController::class)->rosterPayloadPublic($event);
            $payload['roster'] = $rosterData['roster'];
            $payload['event']['rsvp_counts'] = $rosterData['rsvp_counts'];
        }

        if ($tab === 'rules') {
            $payload['rules'] = Rule::query()
                ->where('event_id', $event->id)
                ->orderByDesc('weight')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Rule $r) => [
                    'id' => $r->id,
                    'type' => $r->type->value,
                    'scope' => $r->scope->value,
                    'weight' => $r->weight,
                    'config' => $r->config,
                    'sort_order' => $r->sort_order,
                ]);
        }

        if ($tab === 'drafts') {
            $payload['team_drafts'] = $event->teamDrafts()
                ->where('is_final', false)
                ->with('createdBy:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(function ($d) {
                    $state = is_array($d->state) ? $d->state : [];
                    $violations = $state['violations'] ?? [];

                    return [
                        'id' => $d->id,
                        'name' => $d->name,
                        'is_final' => $d->is_final,
                        'created_at' => $d->created_at?->toIso8601String(),
                        'creator_name' => $d->createdBy?->name,
                        'total_penalty' => $state['total_penalty'] ?? null,
                        'violation_count' => is_array($violations) ? count($violations) : 0,
                        'blocking_errors' => $state['blocking_errors'] ?? [],
                    ];
                });

            if ($event->finalTeamDraft) {
                $payload['final_draft'] = [
                    'id' => $event->finalTeamDraft->id,
                    'state' => $event->finalTeamDraft->state,
                ];
            }
        }

        return Inertia::render('Events/Show', $payload);
    }

    public function update(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $this->validatedEventPayload($request, $organization, $event);
        $previousIds = $validated['previous_event_ids'] ?? [];

        DB::transaction(function () use ($event, $validated, $previousIds): void {
            $event->update([
                'event_type_id' => $validated['event_type_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'event_date' => $validated['event_date'],
                'uses_groups' => (bool) ($validated['uses_groups'] ?? false),
            ]);

            $event->previousEvents()->sync($previousIds);
        });

        return redirect()
            ->back()
            ->with('status', 'Event details saved.');
    }

    public function destroy(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $name = $event->name;
        $event->delete();

        return redirect()
            ->route('organizations.events.index', $organization)
            ->with('status', 'Event "'.$name.'" was deleted.');
    }

    public function duplicate(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicated = DB::transaction(function () use ($event, $validated): Event {
            $copy = $event->replicate([
                'final_team_draft_id',
                'finalized_at',
                'finalized_by',
            ]);
            $copy->name = $validated['name'] ?? ($event->name.' (Copy)');
            $copy->push();

            $copy->previousEvents()->sync($event->previousEvents->pluck('id')->all());

            foreach ($event->rules()->orderBy('sort_order')->get() as $rule) {
                $copy->rules()->create([
                    'type' => $rule->type,
                    'scope' => $rule->scope,
                    'weight' => $rule->weight,
                    'config' => $rule->config,
                    'sort_order' => $rule->sort_order,
                ]);
            }

            foreach ($event->eventMembers as $em) {
                $copy->eventMembers()->create([
                    'member_id' => $em->member_id,
                    'included' => $em->included,
                    'invited' => false,
                    'invited_at' => null,
                    'status' => EventMemberStatus::Pending,
                    'status_changed_at' => now(),
                    'notes' => $em->notes,
                ]);
            }

            $copy->recalculateRuleSortOrders();

            return $copy;
        });

        return redirect()
            ->route('organizations.events.show', [$organization, $duplicated])
            ->with('status', 'Event duplicated as "'.$duplicated->name.'".');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEventPayload(Request $request, Organization $organization, ?Event $event): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['required', 'date'],
            'event_type_id' => ['required', 'integer'],
            'uses_groups' => ['sometimes', 'boolean'],
            'previous_event_ids' => ['nullable', 'array'],
            'previous_event_ids.*' => ['integer', 'distinct'],
        ], [
            'name.required' => 'Event name is required.',
            'event_date.required' => 'Event date is required.',
            'event_date.date' => 'Choose a valid event date.',
            'event_type_id.required' => 'Event type is required.',
        ]);

        $validated['name'] = trim($validated['name']);

        if (! EventType::query()->whereKey($validated['event_type_id'])->where('organization_id', $organization->id)->exists()) {
            throw ValidationException::withMessages(['event_type_id' => 'Invalid event type for this organization.']);
        }

        $usesGroups = (bool) ($validated['uses_groups'] ?? ($event?->uses_groups ?? false));

        $prev = $validated['previous_event_ids'] ?? [];
        if ($prev !== []) {
            $allowed = Event::query()
                ->where('organization_id', $organization->id)
                ->whereIn('id', $prev)
                ->pluck('id')
                ->all();
            if (count($allowed) !== count($prev)) {
                throw ValidationException::withMessages(['previous_event_ids' => 'Previous events must belong to this organization.']);
            }
        }

        if ($event && ! $usesGroups) {
            $hasGroupScope = $event->rules()->where('scope', RuleScope::Group)->exists();
            if ($hasGroupScope) {
                throw ValidationException::withMessages(['uses_groups' => 'Remove group-scoped rules before disabling groups.']);
            }
        }

        $validated['uses_groups'] = $usesGroups;
        $validated['previous_event_ids'] = $prev;

        if ($event) {
            $selfId = $event->id;
            $prev = array_values(array_filter($prev, fn (int $id) => $id !== $selfId));
            $validated['previous_event_ids'] = $prev;
        }

        return $validated;
    }
}
