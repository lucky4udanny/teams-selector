<?php

namespace App\Models;

use App\Enums\EventMemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMemberStatusHistory extends Model
{
    protected $fillable = [
        'event_member_id',
        'status',
        'changed_at',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventMemberStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function eventMember(): BelongsTo
    {
        return $this->belongsTo(EventMember::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
