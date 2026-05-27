/** @typedef {'male' | 'female' | 'non_binary' | 'prefer_not_to_say'} MemberGender */

/** No filter — include all values (non-empty so Listbox option keys stay unique). */
export const FILTER_ANY = '__any__';

/** Filter to rows where the field is null / unset. */
export const FILTER_UNSPECIFIED = '__unspecified__';

/**
 * @typedef {'__any__' | '__unspecified__' | number} SectorFilterValue
 */

/**
 * @typedef {'__any__' | '__unspecified__' | MemberGender} GenderFilterValue
 */

/**
 * @typedef {{
 *   search: string,
 *   sectorId: SectorFilterValue,
 *   gender: GenderFilterValue,
 *   showRemoved: boolean,
 *   panelOpen: boolean,
 * }} MemberFilterPreference
 */

/** @type {MemberFilterPreference} */
export const DEFAULT_MEMBER_FILTERS = {
    search: '',
    sectorId: FILTER_ANY,
    gender: FILTER_ANY,
    showRemoved: false,
    panelOpen: false,
};

const STORAGE_PREFIX = 'teams-selector:member-filters:';

const VALID_GENDERS = new Set(['male', 'female', 'non_binary', 'prefer_not_to_say']);

/**
 * @param {string} orgSlug
 * @returns {string}
 */
export function memberFiltersStorageKey(orgSlug) {
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
 * @param {string} orgSlug
 * @returns {MemberFilterPreference}
 */
export function loadMemberFilterPreference(orgSlug) {
    if (typeof window === 'undefined' || !orgSlug) {
        return { ...DEFAULT_MEMBER_FILTERS };
    }

    try {
        const raw = window.localStorage.getItem(memberFiltersStorageKey(orgSlug));
        if (!raw) {
            return { ...DEFAULT_MEMBER_FILTERS };
        }

        const parsed = JSON.parse(raw);

        return {
            search: typeof parsed?.search === 'string' ? parsed.search : DEFAULT_MEMBER_FILTERS.search,
            sectorId: normalizeSectorFilter(parsed?.sectorId),
            gender: normalizeGenderFilter(parsed?.gender),
            showRemoved: parsed?.showRemoved === true,
            panelOpen: parsed?.panelOpen === true,
        };
    } catch {
        return { ...DEFAULT_MEMBER_FILTERS };
    }
}

/**
 * @param {string} orgSlug
 * @param {MemberFilterPreference} preference
 */
export function saveMemberFilterPreference(orgSlug, preference) {
    if (typeof window === 'undefined' || !orgSlug) {
        return;
    }

    window.localStorage.setItem(
        memberFiltersStorageKey(orgSlug),
        JSON.stringify({
            search: typeof preference.search === 'string' ? preference.search : '',
            sectorId: normalizeSectorFilter(preference.sectorId),
            gender: normalizeGenderFilter(preference.gender),
            showRemoved: preference.showRemoved === true,
            panelOpen: preference.panelOpen === true,
        }),
    );
}

/**
 * @param {Record<string, unknown>} member
 * @param {Pick<MemberFilterPreference, 'search' | 'sectorId' | 'gender' | 'showRemoved'>} filters
 * @returns {boolean}
 */
export function memberMatchesFilters(member, filters) {
    if (!filters.showRemoved && member.deleted_at) {
        return false;
    }

    if (filters.sectorId === FILTER_UNSPECIFIED) {
        if (member.sector_id != null) {
            return false;
        }
    } else if (typeof filters.sectorId === 'number' && member.sector_id !== filters.sectorId) {
        return false;
    }

    if (filters.gender === FILTER_UNSPECIFIED) {
        if (member.gender != null) {
            return false;
        }
    } else if (filters.gender !== FILTER_ANY && member.gender !== filters.gender) {
        return false;
    }

    const q = (filters.search || '').trim().toLowerCase();
    if (q) {
        const haystack = [
            member.first_name,
            member.last_name,
            member.name,
            member.email,
            member.phone,
            member.company,
            member.sector?.name,
        ]
            .map((s) => String(s ?? '').toLowerCase())
            .join(' ');

        if (!haystack.includes(q)) {
            return false;
        }
    }

    return true;
}
