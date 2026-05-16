<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $organization = Organization::factory(),
            'event_type_id' => EventType::factory()->for($organization),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'event_date' => fake()->date(),
            'uses_groups' => false,
            'final_team_draft_id' => null,
            'finalized_at' => null,
            'finalized_by' => null,
        ];
    }
}
