<?php

namespace App\Models;

use Database\Factories\TeamDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamDraft extends Model
{
    /** @use HasFactory<TeamDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'created_by',
        'name',
        'state',
        'is_final',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'is_final' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
