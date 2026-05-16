<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $organization = request()->route('organization');
        if (! $organization instanceof Organization) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->whereKey($value)
            ->where('organization_id', $organization->id)
            ->firstOrFail();
    }
}
