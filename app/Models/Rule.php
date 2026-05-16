<?php

namespace App\Models;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rule extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'scope',
        'weight',
        'config',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'type' => RuleType::class,
            'scope' => RuleScope::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
