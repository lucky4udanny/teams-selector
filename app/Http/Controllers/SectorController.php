<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesOrganizationProps;
use App\Models\Organization;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    use ProvidesOrganizationProps;

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $sectors = $organization->sectors()
            ->orderBy('name')
            ->get()
            ->map(fn (Sector $s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]);

        return Inertia::render('Sectors/Index', [
            'organization' => $this->organizationProps($request, $organization),
            'sectors' => $sectors,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')->where('organization_id', $organization->id),
            ],
        ], [
            'name.required' => 'Sector name is required.',
            'name.unique' => 'A sector with this name already exists.',
        ]);

        $name = trim($validated['name']);
        $organization->sectors()->create(['name' => $name]);

        return redirect()
            ->back()
            ->with('status', 'Sector "'.$name.'" was added.');
    }
}
