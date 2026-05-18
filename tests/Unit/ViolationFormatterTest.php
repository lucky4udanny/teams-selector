<?php

namespace Tests\Unit;

use App\Services\ViolationFormatter;
use Tests\TestCase;

class ViolationFormatterTest extends TestCase
{
    private ViolationFormatter $formatter;

    /** @var array<int, string> */
    private array $memberNames = [
        1 => 'Ivan Dyck',
        2 => 'Jim Froese',
        3 => 'Alice Smith',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ViolationFormatter;
    }

    public function test_formats_member_attribute_with_names_and_score_when_penalty_differs_from_weight(): void
    {
        $text = $this->formatter->format([
            'type' => 'member_attribute',
            'attribute_label' => 'sector',
            'attribute_value_label' => 'Grower',
            'offending_member_ids' => [1, 2],
            'penalty' => 100,
            'weight' => 50,
        ], $this->memberNames);

        $this->assertSame(
            'Ivan Dyck and Jim Froese share sector "Grower" (score: 100)',
            $text,
        );
    }

    public function test_formats_skill_leveling_with_score_suffix(): void
    {
        $text = $this->formatter->format([
            'type' => 'skill_leveling',
            'avg_skill' => 72.4,
            'min_avg' => 35,
            'max_avg' => 65,
            'penalty' => 12,
            'weight' => 50,
        ], $this->memberNames);

        $this->assertSame(
            'Skill levelling: avg skill 72.4 is outside 35–65 (score: 12)',
            $text,
        );
    }

    public function test_formats_banned_pair_without_score_suffix(): void
    {
        $text = $this->formatter->format([
            'type' => 'banned_pair',
            'member_ids' => [1, 2],
            'penalty' => 50,
            'weight' => 50,
        ], $this->memberNames);

        $this->assertSame('Ivan Dyck and Jim Froese are a banned pair', $text);
    }

    public function test_formats_preferred_pair_separated_at_team_level(): void
    {
        $text = $this->formatter->format([
            'type' => 'preferred_pair',
            'scope' => 'team',
            'member_ids' => [1, 2],
            'penalty' => 40,
        ], $this->memberNames);

        $this->assertSame(
            'Ivan Dyck and Jim Froese should be in the same team but are separated',
            $text,
        );
    }

    public function test_formats_repeat_pair(): void
    {
        $text = $this->formatter->format([
            'type' => 'repeat_pair',
            'member_ids' => [2, 3],
            'penalty' => 30,
        ], $this->memberNames);

        $this->assertSame('Jim Froese and Alice Smith were paired in a prior event', $text);
    }

    public function test_formats_team_size(): void
    {
        $text = $this->formatter->format([
            'type' => 'team_size',
            'actual' => 3,
            'expected' => 4,
            'penalty' => 0,
        ], $this->memberNames);

        $this->assertSame(
            'Team has 3 member(s) — rule requires 4 (unavoidable with current member count)',
            $text,
        );
    }

    public function test_formats_group_size(): void
    {
        $text = $this->formatter->format([
            'type' => 'group_size',
            'actual' => 1,
            'expected' => 2,
            'penalty' => 0,
        ], $this->memberNames);

        $this->assertSame(
            'Group has 1 team(s) — rule requires 2 (unavoidable with current member count)',
            $text,
        );
    }

    public function test_enrich_adds_formatted_field(): void
    {
        $enriched = $this->formatter->enrich([
            [
                'type' => 'banned_pair',
                'member_ids' => [1, 2],
                'penalty' => 50,
                'weight' => 50,
            ],
        ], $this->memberNames);

        $this->assertCount(1, $enriched);
        $this->assertSame('Ivan Dyck and Jim Froese are a banned pair', $enriched[0]['formatted']);
    }

    public function test_sanitize_spreadsheet_cell_prefixes_formula_like_values(): void
    {
        $this->assertSame("'=cmd", $this->formatter->sanitizeSpreadsheetCell('=cmd'));
        $this->assertSame('normal', $this->formatter->sanitizeSpreadsheetCell('normal'));
    }
}
