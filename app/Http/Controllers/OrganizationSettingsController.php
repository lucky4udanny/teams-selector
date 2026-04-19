<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganizationSettingsController extends Controller
{
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'brand_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        if (! empty($validated['remove_logo']) && $organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
            $organization->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $path = $request->file('logo')->store('org-logos/'.$organization->id, 'public');
            $organization->logo_path = $path;
        }

        $organization->brand_primary = $validated['brand_primary'] ?? null;
        $organization->brand_accent = $validated['brand_accent'] ?? null;
        $organization->save();

        return redirect()->back();
    }
}
