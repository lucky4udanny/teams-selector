<?php

namespace Database\Seeders;

use App\Enums\EventMemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventType;
use App\Models\Member;
use App\Models\MemberEventTypeSkill;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ],
        );

        $org = Organization::query()->firstOrCreate(
            ['slug' => 'demo-organization'],
            [
                'name' => 'Demo Organization',
                'slug' => 'demo-organization',
            ],
        );

        if (! $org->users()->where('user_id', $user->id)->exists()) {
            $org->users()->attach($user->id, ['role' => OrganizationRole::Admin->value]);
        }

        $typeNames = ['Golf', 'Sales', 'Public Speaking', 'Social Mixer'];
        $types = [];
        foreach ($typeNames as $i => $label) {
            $types[$label] = EventType::query()->firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'name' => $label,
                ],
                [
                    'organization_id' => $org->id,
                    'name' => $label,
                    'sort_order' => $i,
                ]
            );
        }

        $sectorNames = ['Engineering', 'Marketing', 'Operations', 'Executive'];
        $sectors = [];
        foreach ($sectorNames as $name) {
            $sectors[$name] = Sector::query()->firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'name' => $name,
                ],
                [
                    'organization_id' => $org->id,
                    'name' => $name,
                ]
            );
        }

        $memberSeeds = [
            ['Alex', 'Rivera', 'alex@example.test', $sectors['Engineering']->id, ['Golf' => 72, 'Sales' => 45]],
            ['Blake', 'Nguyen', 'blake@example.test', $sectors['Marketing']->id, ['Public Speaking' => 80, 'Social Mixer' => 55]],
            ['Casey', 'Patel', 'casey@example.test', $sectors['Operations']->id, ['Sales' => 65, 'Golf' => 60]],
            ['Dana', 'Brooks', 'dana@example.test', $sectors['Executive']->id, ['Golf' => 88, 'Public Speaking' => 70]],
            ['Ellis', 'Moore', 'ellis@example.test', $sectors['Marketing']->id, ['Social Mixer' => 90, 'Sales' => 40]],
            ['Fran', 'Lopez', 'fran@example.test', $sectors['Engineering']->id, ['Public Speaking' => 55, 'Golf' => 68]],
        ];

        $members = [];
        foreach ($memberSeeds as $seed) {
            [$first, $last, $email, $sectorId, $skillMap] = $seed;
            $member = Member::query()->firstOrCreate(
                [
                    'organization_id' => $org->id,
                    'email' => $email,
                ],
                [
                    'organization_id' => $org->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $email,
                    'phone' => null,
                    'company' => 'Demo Co',
                    'sector_id' => $sectorId,
                    'notes' => 'Seeded member',
                ]
            );
            $members[] = $member;

            foreach ($skillMap as $typeName => $level) {
                $tid = $types[$typeName]->id;
                MemberEventTypeSkill::query()->updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'event_type_id' => $tid,
                    ],
                    ['skill_level' => $level],
                );
            }
        }

        $golfType = $types['Golf'];
        $eventDate = Carbon::now()->addMonth();

        $event = Event::query()->firstOrCreate(
            [
                'organization_id' => $org->id,
                'name' => 'Spring Scramble',
            ],
            [
                'organization_id' => $org->id,
                'event_type_id' => $golfType->id,
                'name' => 'Spring Scramble',
                'description' => 'Sample outing with seeded roster.',
                'event_date' => $eventDate->toDateString(),
                'uses_groups' => false,
                'final_team_draft_id' => null,
                'finalized_at' => null,
                'finalized_by' => null,
            ],
        );

        foreach ($members as $member) {
            EventMember::query()->firstOrCreate(
                [
                    'event_id' => $event->id,
                    'member_id' => $member->id,
                ],
                [
                    'included' => true,
                    'invited' => true,
                    'invited_at' => now(),
                    'status' => EventMemberStatus::Accepted,
                    'status_changed_at' => now(),
                    'notes' => null,
                ],
            );
        }
    }
}
