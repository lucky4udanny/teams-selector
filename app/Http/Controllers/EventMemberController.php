<?php

namespace App\Http\Controllers;

use App\Enums\EventMemberStatus;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventMemberController extends Controller
{
    public function index(Request $request, Organization $organization, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json($this->rosterPayload($event));
    }

    public function store(Request $request, Organization $organization, Event $event): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = Member::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $validated['member_ids'])
            ->pluck('id')
            ->all();

        if (count($ids) !== count($validated['member_ids'])) {
            abort(422, 'One or more members are not in this organization.');
        }

        foreach ($ids as $memberId) {
            $event->eventMembers()->firstOrCreate(
                ['member_id' => $memberId],
                [
                    'included' => true,
                    'invited' => false,
                    'invited_at' => null,
                    'status' => EventMemberStatus::Pending,
                    'status_changed_at' => now(),
                    'notes' => null,
                ]
            );
        }

        if ($request->wantsJson()) {
            return response()->json($this->rosterPayload($event));
        }

        return redirect()->back();
    }

    public function copyFromEvent(Request $request, Organization $organization, Event $event): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $source = Event::query()->findOrFail($validated['source_event_id']);
        if ((int) $source->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $rows = $source->eventMembers()->get();
        foreach ($rows as $row) {
            $event->eventMembers()->firstOrCreate(
                ['member_id' => $row->member_id],
                [
                    'included' => $row->included,
                    'invited' => false,
                    'invited_at' => null,
                    'status' => EventMemberStatus::Pending,
                    'status_changed_at' => now(),
                    'notes' => $row->notes,
                ]
            );
        }

        if ($request->wantsJson()) {
            return response()->json($this->rosterPayload($event));
        }

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, Event $event, EventMember $eventMember): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'included' => ['sometimes', 'boolean'],
            'invited' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:'.implode(',', array_column(EventMemberStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $this->applyEventMemberUpdate($request, $eventMember, $validated);

        if ($request->wantsJson()) {
            return response()->json($this->rosterPayload($event));
        }

        return redirect()->back();
    }

    public function bulkUpdate(Request $request, Organization $organization, Event $event): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.included' => ['sometimes', 'boolean'],
            'items.*.invited' => ['sometimes', 'boolean'],
            'items.*.status' => ['sometimes', 'in:'.implode(',', array_column(EventMemberStatus::cases(), 'value'))],
            'items.*.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $event, $validated): void {
            foreach ($validated['items'] as $item) {
                /** @var EventMember|null $em */
                $em = $event->eventMembers()->whereKey($item['id'])->first();
                if (! $em) {
                    continue;
                }
                $this->applyEventMemberUpdate($request, $em, $item);
            }
        });

        if ($request->wantsJson()) {
            return response()->json($this->rosterPayload($event));
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, Event $event, EventMember $eventMember): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $event);

        $eventMember->delete();

        if ($request->wantsJson()) {
            return response()->json($this->rosterPayload($event));
        }

        return redirect()->back();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyEventMemberUpdate(Request $request, EventMember $eventMember, array $validated): void
    {
        $user = $request->user();
        $oldStatus = $eventMember->status;

        $updates = [];
        if (array_key_exists('included', $validated)) {
            $updates['included'] = (bool) $validated['included'];
        }
        if (array_key_exists('invited', $validated)) {
            $updates['invited'] = (bool) $validated['invited'];
            $updates['invited_at'] = $validated['invited'] ? ($eventMember->invited_at ?? now()) : null;
        }
        if (array_key_exists('status', $validated)) {
            $updates['status'] = EventMemberStatus::from($validated['status']);
            $updates['status_changed_at'] = now();
        }
        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        if ($updates === []) {
            return;
        }

        $eventMember->update($updates);
        $eventMember->refresh();

        if (isset($updates['status']) && $oldStatus !== $eventMember->status) {
            $eventMember->statusHistories()->create([
                'status' => $eventMember->status,
                'changed_at' => now(),
                'changed_by' => $user?->id,
            ]);
        }
    }

    /**
     * @return array{roster: list<array<string, mixed>>, rsvp_counts: array<string, int>}
     */
    public function rosterPayloadPublic(Event $event): array
    {
        return $this->rosterPayload($event);
    }

    private function rosterPayload(Event $event): array
    {
        $event->load([
            'eventMembers.member.sector',
            'eventMembers.member.memberEventTypeSkills' => fn ($q) => $q->where('event_type_id', $event->event_type_id),
        ]);

        $roster = $event->eventMembers->map(function (EventMember $em) {
            $m = $em->member;
            $skill = $m->memberEventTypeSkills->first();

            return [
                'id' => $em->id,
                'member_id' => $m->id,
                'first_name' => $m->first_name,
                'last_name' => $m->last_name,
                'email' => $m->email,
                'display_name' => $m->displayName(),
                'sector' => $m->sector?->only(['id', 'name']),
                'skill_level' => $skill?->skill_level,
                'included' => $em->included,
                'invited' => $em->invited,
                'invited_at' => $em->invited_at?->toIso8601String(),
                'status' => $em->status->value,
                'status_changed_at' => $em->status_changed_at?->toIso8601String(),
                'notes' => $em->notes,
            ];
        })->values()->all();

        return [
            'roster' => $roster,
            'rsvp_counts' => $event->rsvpCounts(),
        ];
    }
}
