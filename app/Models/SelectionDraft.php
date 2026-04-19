<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectionDraft extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'state',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedSelections(): HasMany
    {
        return $this->hasMany(ApprovedSelection::class);
    }
}
