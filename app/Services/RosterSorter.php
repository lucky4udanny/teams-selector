<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Roster sort — mirrors {@see resources/js/utils/rosterSort.js}.
 */
final class RosterSorter
{
    private const VALID_COLUMNS = [
        'last_name',
        'first_name',
        'email',
        'sector',
        'status',
        'invited',
        'included',
        'notes',
    ];

    private const STATUS_ORDER = [
        'accepted' => 0,
        'pending' => 1,
        'declined' => 2,
    ];

    public function __construct(
        public readonly string $column,
        public readonly string $direction,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $column = (string) $request->query('sort_column', 'last_name');
        if (! in_array($column, self::VALID_COLUMNS, true)) {
            $column = 'last_name';
        }

        $direction = $request->query('sort_direction') === 'desc' ? 'desc' : 'asc';

        return new self($column, $direction);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function sort(array $rows): array
    {
        $dir = $this->direction === 'desc' ? -1 : 1;

        usort($rows, function (array $a, array $b) use ($dir): int {
            $primary = $this->compare($this->sortValue($a, $this->column), $this->sortValue($b, $this->column)) * $dir;
            if ($primary !== 0) {
                return $primary;
            }

            $last = $this->compare($this->sortValue($a, 'last_name'), $this->sortValue($b, 'last_name'));
            if ($last !== 0) {
                return $last;
            }

            $first = $this->compare($this->sortValue($a, 'first_name'), $this->sortValue($b, 'first_name'));
            if ($first !== 0) {
                return $first;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function sortValue(array $row, string $column): string|int
    {
        return match ($column) {
            'first_name' => strtolower(trim((string) ($row['first_name'] ?? ''))),
            'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            'sector' => strtolower(trim((string) ($row['sector']['name'] ?? ''))),
            'status' => self::STATUS_ORDER[$row['status'] ?? ''] ?? 9,
            'invited' => ($row['invited'] ?? false) ? 1 : 0,
            'included' => ($row['included'] ?? false) ? 1 : 0,
            'notes' => strtolower(trim((string) ($row['notes'] ?? ''))),
            default => strtolower(trim((string) ($row['last_name'] ?? ''))),
        };
    }

    private function compare(string|int $a, string|int $b): int
    {
        if (is_int($a) && is_int($b)) {
            return $a <=> $b;
        }

        return strnatcasecmp((string) $a, (string) $b);
    }
}
