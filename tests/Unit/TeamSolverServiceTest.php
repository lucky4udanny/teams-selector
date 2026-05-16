<?php

namespace Tests\Unit;

use App\Services\TeamSolverService;
use ReflectionMethod;
use Tests\TestCase;

class TeamSolverServiceTest extends TestCase
{
    public function test_members_by_group_skips_invalid_team_indices(): void
    {
        $solver = app(TeamSolverService::class);
        $method = new ReflectionMethod(TeamSolverService::class, 'membersByGroup');
        $method->setAccessible(true);

        $teams = [
            ['member_ids' => [1, 2]],
            ['member_ids' => [3, 4]],
        ];
        $groups = [
            ['team_indices' => [0, 99]],
        ];

        $result = $method->invoke($solver, $teams, $groups);

        $this->assertSame([[1, 2]], $result);
    }
}
