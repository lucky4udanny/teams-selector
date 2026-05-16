<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\EventType;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventTypeController extends Controller
{
    use ProvidesOrganizationProps;

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $types = $organization->eventTypes()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (EventType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'sort_order' => $t->sort_order,
            ]);

        return Inertia::render('EventTypes/Index', [
            'organization' => $this->organizationProps($request, $organization),
            'eventTypes' => $types,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $this->validatedPayload($request, $organization);

        $max = (int) $organization->eventTypes()->max('sort_order');
        $organization->eventTypes()->create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? ($max + 1),
        ]);

        return redirect()
            ->back()
            ->with('status', 'Event type "'.$validated['name'].'" was added.');
    }

    public function update(Request $request, Organization $organization, EventType $eventType): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $this->validatedPayload($request, $organization, $eventType);

        $eventType->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $eventType->sort_order,
        ]);

        return redirect()
            ->back()
            ->with('status', 'Event type "'.$validated['name'].'" was updated.');
    }

    public function destroy(Request $request, Organization $organization, EventType $eventType): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        if ($eventType->events()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Cannot delete "'.$eventType->name.'" because events use this type. Change those events first.');
        }

        $name = $eventType->name;
        $eventType->delete();

        return redirect()
            ->back()
            ->with('status', 'Event type "'.$name.'" was deleted.');
    }

    /**
     * @return array{name: string, sort_order: int|null}
     */
    private function validatedPayload(Request $request, Organization $organization, ?EventType $eventType = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_types', 'name')
                    ->where('organization_id', $organization->id)
                    ->whereNull('deleted_at')
                    ->ignore($eventType?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'name.required' => 'Name is required.',
            'name.unique' => 'An event type with this name already exists.',
            'sort_order.integer' => 'Sort order must be a whole number.',
            'sort_order.min' => 'Sort order must be at least 0.',
            'sort_order.max' => 'Sort order must be 65535 or less.',
        ]);

        $validated['name'] = trim($validated['name']);

        return $validated;
    }
}
