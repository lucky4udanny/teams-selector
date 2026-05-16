<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\EventType;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $max = (int) $organization->eventTypes()->max('sort_order');
        $organization->eventTypes()->create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? ($max + 1),
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Organization $organization, EventType $eventType): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $eventType->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $eventType->sort_order,
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, Organization $organization, EventType $eventType): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $eventType->delete();

        return redirect()->back();
    }
}
