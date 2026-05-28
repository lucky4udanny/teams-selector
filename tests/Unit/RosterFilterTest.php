<?php

namespace Tests\Unit;

use App\Models\Member;
use App\Services\RosterFilter;
use App\Services\RosterFilterCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RosterFilterTest extends TestCase
{
    #[DataProvider('filterCases')]
    public function test_roster_filter_matches_ui_rules(
        array $row,
        ?string $memberGender,
        RosterFilterCriteria $filters,
        bool $expected,
    ): void {
        $orgMember = null;
        if ($memberGender !== null) {
            $orgMember = new Member(['gender' => $memberGender]);
        }

        $this->assertSame($expected, RosterFilter::matches($row, $orgMember, $filters));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string, 2: RosterFilterCriteria, 3: bool}>
     */
    public static function filterCases(): array
    {
        $baseRow = [
            'display_name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'sector' => ['id' => 5, 'name' => 'Legal'],
            'invited' => true,
            'status' => 'accepted',
            'notes' => 'VIP',
        ];

        return [
            'status match' => [
                $baseRow,
                'female',
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, 'accepted'),
                true,
            ],
            'status mismatch' => [
                $baseRow,
                'female',
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, 'pending'),
                false,
            ],
            'invited yes' => [
                $baseRow,
                null,
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_INVITED_YES, RosterFilterCriteria::FILTER_ANY),
                true,
            ],
            'invited no rejects invited row' => [
                $baseRow,
                null,
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_INVITED_NO, RosterFilterCriteria::FILTER_ANY),
                false,
            ],
            'gender from org member' => [
                $baseRow,
                'female',
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, 'female', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY),
                true,
            ],
            'unspecified gender requires null' => [
                $baseRow,
                'male',
                new RosterFilterCriteria('', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_UNSPECIFIED, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY),
                false,
            ],
            'search in notes' => [
                $baseRow,
                null,
                new RosterFilterCriteria('vip', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY),
                true,
            ],
            'sector id match' => [
                $baseRow,
                null,
                new RosterFilterCriteria('', '5', RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY, RosterFilterCriteria::FILTER_ANY),
                true,
            ],
        ];
    }
}
