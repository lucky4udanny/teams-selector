<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrganizationSettingsController extends Controller
{
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'brand_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
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

            $file = $request->file('logo');
            $dir = 'org-logos/'.$organization->id;

            if ($file->getMimeType() === 'image/svg+xml') {
                $sanitizer = new Sanitizer();
                $clean = $sanitizer->sanitize((string) file_get_contents($file->getRealPath()));
                $relativePath = $dir.'/'.Str::random(40).'.svg';
                Storage::disk('public')->put($relativePath, $clean);
                $organization->logo_path = $relativePath;
            } else {
                $organization->logo_path = $file->store($dir, 'public');
            }
        }

        $organization->brand_primary = $validated['brand_primary'] ?? null;
        $organization->brand_accent = $validated['brand_accent'] ?? null;
        $organization->save();

        return redirect()->back();
    }
}
