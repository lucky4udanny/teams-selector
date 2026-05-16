<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TeamDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamDraft>
 */
class TeamDraftFactory extends Factory
{
    protected $model = TeamDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'created_by' => User::factory(),
            'name' => fake()->optional()->words(2, true),
            'state' => [
                'teams' => [],
                'groups' => [],
            ],
            'is_final' => false,
        ];
    }
}
