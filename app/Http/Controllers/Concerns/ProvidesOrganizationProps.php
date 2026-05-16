<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use Illuminate\Http\Request;

trait ProvidesOrganizationProps
{
    /**
     * @return array<string, mixed>
     */
    protected function organizationProps(Request $request, Organization $organization): array
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
