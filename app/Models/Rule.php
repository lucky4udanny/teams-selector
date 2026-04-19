<?php

namespace App\Models;

use App\Enums\RuleScope;
use App\Enums\RuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rule extends Model
{
    protected $fillable = [
        'organization_id',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
