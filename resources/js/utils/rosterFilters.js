import { FILTER_ANY, FILTER_UNSPECIFIED } from '@/utils/memberFilters';

export { FILTER_ANY, FILTER_UNSPECIFIED };

/** @typedef {'male' | 'female' | 'non_binary' | 'prefer_not_to_say'} MemberGender */

/** @typedef {'pending' | 'accepted' | 'declined'} RosterStatus */

export const FILTER_INVITED_YES = '__invited_yes__';
export const FILTER_INVITED_NO = '__invited_no__';

/**
 * @typedef {'__any__' | '__unspecified__' | number} SectorFilterValue
 */

/**
 * @typedef {'__any__' | '__unspecified__' | MemberGender} GenderFilterValue
 */

/**
 * @typedef {'__any__' | '__invited_yes__' | '__invited_no__'} InvitedFilterValue
 */

/**
 * @typedef {'__any__' | RosterStatus} StatusFilterValue
 */

/**
 * @typedef {{
 *   search: string,
 *   sectorId: SectorFilterValue,
 *   gender: GenderFilterValue,
 *   invited: InvitedFilterValue,
 *   status: StatusFilterValue,
 *   panelOpen: boolean,
 * }} RosterFilterPreference
 */

/** @type {RosterFilterPreference} */
export const DEFAULT_ROSTER_FILTERS = {
    search: '',
    sectorId: FILTER_ANY,
    gender: FILTER_ANY,
    invited: FILTER_ANY,
    status: FILTER_ANY,
    panelOpen: false,
};

const STORAGE_PREFIX = 'teams-selector:roster-filters:';

const VALID_GENDERS = new Set(['male', 'female', 'non_binary', 'prefer_not_to_say']);
const VALID_STATUSES = new Set(['pending', 'accepted', 'declined']);

/**
 * @param {string} orgSlug
 * @returns {string}
 */
export function rosterFiltersStorageKey(orgSlug) {
    return `${STORAGE_PREFIX}${orgSlug}`;
}

/**
 * @param {unknown} value
 * @returns {SectorFilterValue}
 */
function normalizeSectorFilter(value) {
    if (value === FILTER_UNSPECIFIED) {
        return FILTER_UNSPECIFIED;
    }

    if (value === null || value === undefined || value === '' || value === FILTER_ANY) {
        return FILTER_ANY;
    }

    const n = Number(value);

    return Number.isFinite(n) ? n : FILTER_ANY;
}

/**
 * @param {unknown} value
 * @returns {GenderFilterValue}
 */
function normalizeGenderFilter(value) {
    if (value === FILTER_UNSPECIFIED) {
        return FILTER_UNSPECIFIED;
    }

    if (value === null || value === undefined || value === '' || value === FILTER_ANY) {
        return FILTER_ANY;
    }

    return VALID_GENDERS.has(value) ? /** @type {MemberGender} */ (value) : FILTER_ANY;
}

/**
 * @param {unknown} value
 * @returns {InvitedFilterValue}
 */
function normalizeInvitedFilter(value) {
    if (value === true || value === FILTER_INVITED_YES || value === 'true') {
        return FILTER_INVITED_YES;
    }

    if (value === false || value === FILTER_INVITED_NO || value === 'false') {
        return FILTER_INVITED_NO;
    }

    return FILTER_ANY;
}

/**
 * @param {unknown} value
 * @returns {StatusFilterValue}
 */
function normalizeStatusFilter(value) {
    if (value === null || value === undefined || value === '' || value === FILTER_ANY) {
        return FILTER_ANY;
    }

    return VALID_STATUSES.has(value) ? /** @type {RosterStatus} */ (value) : FILTER_ANY;
}

/**
 * @param {string} orgSlug
 * @returns {RosterFilterPreference}
 */
export function loadRosterFilterPreference(orgSlug) {
    if (typeof window === 'undefined' || !orgSlug) {
        return { ...DEFAULT_ROSTER_FILTERS };
    }

    try {
        const raw = window.localStorage.getItem(rosterFiltersStorageKey(orgSlug));
        if (!raw) {
            return { ...DEFAULT_ROSTER_FILTERS };
        }

        const parsed = JSON.parse(raw);

        return {
            search: typeof parsed?.search === 'string' ? parsed.search : DEFAULT_ROSTER_FILTERS.search,
            sectorId: normalizeSectorFilter(parsed?.sectorId),
            gender: normalizeGenderFilter(parsed?.gender),
            invited: normalizeInvitedFilter(parsed?.invited),
            status: normalizeStatusFilter(parsed?.status),
            panelOpen: parsed?.panelOpen === true,
        };
    } catch {
        return { ...DEFAULT_ROSTER_FILTERS };
    }
}

/**
 * @param {string} orgSlug
 * @param {RosterFilterPreference} preference
 */
export function saveRosterFilterPreference(orgSlug, preference) {
    if (typeof window === 'undefined' || !orgSlug) {
        return;
    }

    window.localStorage.setItem(
        rosterFiltersStorageKey(orgSlug),
        JSON.stringify({
            search: typeof preference.search === 'string' ? preference.search : '',
            sectorId: normalizeSectorFilter(preference.sectorId),
            gender: normalizeGenderFilter(preference.gender),
            invited: normalizeInvitedFilter(preference.invited),
            status: normalizeStatusFilter(preference.status),
            panelOpen: preference.panelOpen === true,
        }),
    );
}

/**
 * @param {Record<string, unknown>} row
 * @param {Record<string, unknown> | null | undefined} orgMember
 * @param {Pick<RosterFilterPreference, 'search' | 'sectorId' | 'gender' | 'invited' | 'status'>} filters
 * @returns {boolean}
 */
export function rosterMatchesFilters(row, orgMember, filters) {
    const sectorId = row.sector?.id ?? orgMember?.sector?.id ?? null;
    const gender = orgMember?.gender ?? null;

    if (filters.sectorId === FILTER_UNSPECIFIED) {
        if (sectorId != null) {
            return false;
        }
    } else if (typeof filters.sectorId === 'number' && sectorId !== filters.sectorId) {
        return false;
    }

    if (filters.gender === FILTER_UNSPECIFIED) {
        if (gender != null) {
            return false;
        }
    } else if (filters.gender !== FILTER_ANY && gender !== filters.gender) {
        return false;
    }

    if (filters.invited === FILTER_INVITED_YES && !row.invited) {
        return false;
    }

    if (filters.invited === FILTER_INVITED_NO && row.invited) {
        return false;
    }

    if (filters.status !== FILTER_ANY && row.status !== filters.status) {
        return false;
    }

    const q = (filters.search || '').trim().toLowerCase();
    if (q) {
        const haystack = [
            row.display_name,
            row.first_name,
            row.last_name,
            row.email,
            row.sector?.name ?? orgMember?.sector?.name,
            row.notes,
        ]
            .map((s) => String(s ?? '').toLowerCase())
            .join(' ');

        if (!haystack.includes(q)) {
            return false;
        }
    }

    return true;
}
