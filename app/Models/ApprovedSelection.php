<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedSelection extends Model
{
    protected $fillable = [
        'organization_id',
        'selection_draft_id',
        'approved_by',
        'title',
        'notes',
        'occurred_at',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(SelectionDraft::class, 'selection_draft_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
