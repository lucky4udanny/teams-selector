<?php

namespace App\Models;

use Database\Factories\EventTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventType extends Model
{
    /** @use HasFactory<EventTypeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function memberSkills(): HasMany
    {
        return $this->hasMany(MemberEventTypeSkill::class);
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
