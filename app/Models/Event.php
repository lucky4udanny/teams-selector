<?php

namespace App\Models;

use App\Enums\EventMemberStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'event_type_id',
        'name',
        'description',
        'event_date',
        'uses_groups',
        'final_team_draft_id',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'uses_groups' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function finalTeamDraft(): BelongsTo
    {
        return $this->belongsTo(TeamDraft::class, 'final_team_draft_id');
    }

    public function eventMembers(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_members')
            ->withPivot([
                'included',
                'invited',
                'invited_at',
                'status',
                'status_changed_at',
                'notes',
            ])
            ->withTimestamps();
    }

    public function previousEvents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'event_previous_event',
            'event_id',
            'previous_event_id'
        )->withTimestamps();
    }

    public function successorEvents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'event_previous_event',
            'previous_event_id',
            'event_id'
        )->withTimestamps();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class)->orderBy('sort_order');
    }

    public function teamDrafts(): HasMany
    {
        return $this->hasMany(TeamDraft::class);
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

    /**
     * @return array{pending: int, accepted: int, declined: int, invited: int}
     */
    public function rsvpCounts(): array
    {
        $rows = $this->eventMembers()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $invited = (int) $this->eventMembers()->where('invited', true)->count();

        return [
            'pending' => (int) ($rows[EventMemberStatus::Pending->value] ?? 0),
            'accepted' => (int) ($rows[EventMemberStatus::Accepted->value] ?? 0),
            'declined' => (int) ($rows[EventMemberStatus::Declined->value] ?? 0),
            'invited' => $invited,
        ];
    }
}
