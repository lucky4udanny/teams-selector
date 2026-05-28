<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Roster filter query params — mirrors {@see resources/js/utils/rosterFilters.js}.
 */
final class RosterFilterCriteria
{
    public const FILTER_ANY = '__any__';

    public const FILTER_UNSPECIFIED = '__unspecified__';

    public const FILTER_INVITED_YES = '__invited_yes__';

    public const FILTER_INVITED_NO = '__invited_no__';

    private const VALID_GENDERS = ['male', 'female', 'non_binary', 'prefer_not_to_say'];

    private const VALID_STATUSES = ['pending', 'accepted', 'declined'];

    public function __construct(
        public readonly string $search,
        public readonly string $sectorId,
        public readonly string $gender,
        public readonly string $invited,
        public readonly string $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: trim((string) $request->query('search', '')),
            sectorId: self::normalizeSectorFilter($request->query('sector_id')),
            gender: self::normalizeGenderFilter($request->query('gender')),
            invited: self::normalizeInvitedFilter($request->query('invited')),
            status: self::normalizeStatusFilter($request->query('status')),
        );
    }

    public static function default(): self
    {
        return new self(
            search: '',
            sectorId: self::FILTER_ANY,
            gender: self::FILTER_ANY,
            invited: self::FILTER_ANY,
            status: self::FILTER_ANY,
        );
    }

    private static function normalizeSectorFilter(mixed $value): string
    {
        if ($value === self::FILTER_UNSPECIFIED) {
            return self::FILTER_UNSPECIFIED;
        }

        if ($value === null || $value === '' || $value === self::FILTER_ANY) {
            return self::FILTER_ANY;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (string) (int) $value;
        }

        return self::FILTER_ANY;
    }

    private static function normalizeGenderFilter(mixed $value): string
    {
        if ($value === self::FILTER_UNSPECIFIED) {
            return self::FILTER_UNSPECIFIED;
        }

        if ($value === null || $value === '' || $value === self::FILTER_ANY) {
            return self::FILTER_ANY;
        }

        $s = (string) $value;

        return in_array($s, self::VALID_GENDERS, true) ? $s : self::FILTER_ANY;
    }

    private static function normalizeInvitedFilter(mixed $value): string
    {
        if ($value === true || $value === 'true' || $value === self::FILTER_INVITED_YES) {
            return self::FILTER_INVITED_YES;
        }

        if ($value === false || $value === 'false' || $value === self::FILTER_INVITED_NO) {
            return self::FILTER_INVITED_NO;
        }

        return self::FILTER_ANY;
    }

    private static function normalizeStatusFilter(mixed $value): string
    {
        if ($value === null || $value === '' || $value === self::FILTER_ANY) {
            return self::FILTER_ANY;
        }

        $s = (string) $value;

        return in_array($s, self::VALID_STATUSES, true) ? $s : self::FILTER_ANY;
    }

    public function sectorIdAsInt(): ?int
    {
        if ($this->sectorId === self::FILTER_ANY || $this->sectorId === self::FILTER_UNSPECIFIED) {
            return null;
        }

        return (int) $this->sectorId;
    }
}
