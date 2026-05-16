<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_create_event_type_with_flash(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $response = $this->actingAs($user)->post(
            route('organizations.event-types.store', $org),
            ['name' => 'Golf', 'sort_order' => 1],
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Event type "Golf" was added.');
        $this->assertDatabaseHas('event_types', [
            'organization_id' => $org->id,
            'name' => 'Golf',
            'sort_order' => 1,
        ]);
    }

    public function test_duplicate_name_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);
        EventType::factory()->create(['organization_id' => $org->id, 'name' => 'Golf']);

        $response = $this->actingAs($user)->from(route('organizations.event-types.index', $org))
            ->post(route('organizations.event-types.store', $org), ['name' => 'Golf']);

        $response->assertRedirect(route('organizations.event-types.index', $org));
        $response->assertSessionHasErrors(['name' => 'An event type with this name already exists.']);
    }

    public function test_cannot_delete_event_type_used_by_events(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);
        $type = EventType::factory()->create(['organization_id' => $org->id, 'name' => 'Golf']);
        Event::factory()->create([
            'organization_id' => $org->id,
            'event_type_id' => $type->id,
        ]);

        $response = $this->actingAs($user)->from(route('organizations.event-types.index', $org))
            ->delete(route('organizations.event-types.destroy', [$org, $type]));

        $response->assertRedirect(route('organizations.event-types.index', $org));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('event_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_invalid_sort_order_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $org->users()->attach($user->id, ['role' => OrganizationRole::Organizer->value]);

        $response = $this->actingAs($user)->from(route('organizations.event-types.index', $org))
            ->post(route('organizations.event-types.store', $org), [
                'name' => 'Dinner',
                'sort_order' => 'abc',
            ]);

        $response->assertSessionHasErrors('sort_order');
    }
}
