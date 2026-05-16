<?php

namespace App\Models;

use App\Enums\EventMemberStatus;
use Database\Factories\EventMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventMember extends Model
{
    /** @use HasFactory<EventMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'member_id',
        'included',
        'invited',
        'invited_at',
        'status',
        'status_changed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'included' => 'boolean',
            'invited' => 'boolean',
            'invited_at' => 'datetime',
            'status' => EventMemberStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EventMemberStatusHistory::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $event = request()->route('event');
        if (! $event instanceof Event) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->whereKey($value)
            ->where('event_id', $event->id)
            ->firstOrFail();
    }
}
