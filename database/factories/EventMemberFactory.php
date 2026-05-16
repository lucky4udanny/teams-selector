<?php

namespace Database\Factories;

use App\Enums\EventMemberStatus;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventMember>
 */
class EventMemberFactory extends Factory
{
    protected $model = EventMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'member_id' => Member::factory(),
            'included' => true,
            'invited' => false,
            'invited_at' => null,
            'status' => EventMemberStatus::Pending,
            'status_changed_at' => null,
            'notes' => null,
        ];
    }
}
