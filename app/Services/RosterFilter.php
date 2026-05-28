<?php

namespace App\Services;

use App\Models\Member;

/**
 * Client roster filter logic — mirrors {@see resources/js/utils/rosterFilters.js} rosterMatchesFilters().
 */
final class RosterFilter
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function matches(array $row, ?Member $orgMember, RosterFilterCriteria $filters): bool
    {
        $sectorId = $row['sector']['id'] ?? $orgMember?->sector_id ?? null;
        $gender = $orgMember?->gender;

        if ($filters->sectorId === RosterFilterCriteria::FILTER_UNSPECIFIED) {
            if ($sectorId !== null) {
                return false;
            }
        } elseif ($filters->sectorIdAsInt() !== null && (int) $sectorId !== $filters->sectorIdAsInt()) {
            return false;
        }

        if ($filters->gender === RosterFilterCriteria::FILTER_UNSPECIFIED) {
            if ($gender !== null) {
                return false;
            }
        } elseif ($filters->gender !== RosterFilterCriteria::FILTER_ANY && $gender !== $filters->gender) {
            return false;
        }

        $invited = (bool) ($row['invited'] ?? false);

        if ($filters->invited === RosterFilterCriteria::FILTER_INVITED_YES && ! $invited) {
            return false;
        }

        if ($filters->invited === RosterFilterCriteria::FILTER_INVITED_NO && $invited) {
            return false;
        }

        if ($filters->status !== RosterFilterCriteria::FILTER_ANY && ($row['status'] ?? '') !== $filters->status) {
            return false;
        }

        $q = strtolower(trim($filters->search));
        if ($q !== '') {
            $sectorName = $row['sector']['name'] ?? $orgMember?->sector?->name ?? '';
            $haystack = strtolower(implode(' ', array_filter([
                (string) ($row['display_name'] ?? ''),
                (string) ($row['first_name'] ?? ''),
                (string) ($row['last_name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) $sectorName,
                (string) ($row['notes'] ?? ''),
            ], fn (string $s) => $s !== '')));

            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        return true;
    }
}
