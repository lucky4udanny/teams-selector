<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEventTypeSkill extends Model
{
    protected $fillable = [
        'member_id',
        'event_type_id',
        'skill_level',
    ];

    protected function casts(): array
    {
        return [
            'skill_level' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }
}
