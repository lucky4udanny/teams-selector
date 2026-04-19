<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Organization extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'brand_primary',
        'brand_accent',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $org): void {
            if ($org->slug === null || $org->slug === '') {
                $org->slug = Str::slug($org->name).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class)->orderBy('sort_order');
    }

    public function selectionDrafts(): HasMany
    {
        return $this->hasMany(SelectionDraft::class);
    }

    public function approvedSelections(): HasMany
    {
        return $this->hasMany(ApprovedSelection::class)->orderByDesc('created_at');
    }

    public function roleFor(User $user): ?OrganizationRole
    {
        $row = $this->users()->wherePivot('user_id', $user->id)->first();

        return $row?->pivot
            ? OrganizationRole::from((string) $row->pivot->role)
            : null;
    }

    public function logoPublicUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function brandingPayload(): array
    {
        return [
            'logo_url' => $this->logoPublicUrl(),
            'logo_path' => $this->logo_path,
            'brand_primary' => $this->brand_primary,
            'brand_accent' => $this->brand_accent,
        ];
    }
}
