<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Member $member): void {
            $member->eventMembers()->delete();
        });
    }

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'gender',
        'phone',
        'company',
        'sector_id',
        'email',
        'notes',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function eventMembers(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_members')->withTimestamps();
    }

    public function memberEventTypeSkills(): HasMany
    {
        return $this->hasMany(MemberEventTypeSkill::class);
    }

    public function displayName(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) ($this->last_name ?? ''));

        return trim($first.' '.$last);
    }
}
