/** @typedef {'asc' | 'desc'} MemberSortDirection */

/** @typedef {'name' | 'first_name' | 'company' | 'sector'} MemberSortKey */

/**
 * @typedef {{ key: MemberSortKey, dir: MemberSortDirection }} MemberSortPreference
 */

/** @type {MemberSortPreference} */
export const DEFAULT_MEMBER_SORT = { key: 'name', dir: 'asc' };

const STORAGE_PREFIX = 'teams-selector:member-sort:';

const VALID_KEYS = new Set(['name', 'first_name', 'company', 'sector']);

/**
 * @param {string} orgSlug
 * @returns {string}
 */
export function memberSortStorageKey(orgSlug) {
    return `${STORAGE_PREFIX}${orgSlug}`;
}

/**
 * @param {string} orgSlug
 * @returns {MemberSortPreference}
 */
export function loadMemberSortPreference(orgSlug) {
    if (typeof window === 'undefined' || !orgSlug) {
        return { ...DEFAULT_MEMBER_SORT };
    }

    try {
        const raw = window.localStorage.getItem(memberSortStorageKey(orgSlug));
        if (!raw) {
            return { ...DEFAULT_MEMBER_SORT };
        }

        const parsed = JSON.parse(raw);
        const key = VALID_KEYS.has(parsed?.key) ? parsed.key : DEFAULT_MEMBER_SORT.key;
        const dir = parsed?.dir === 'desc' ? 'desc' : 'asc';

        return { key, dir };
    } catch {
        return { ...DEFAULT_MEMBER_SORT };
    }
}

/**
 * @param {string} orgSlug
 * @param {MemberSortPreference} preference
 */
export function saveMemberSortPreference(orgSlug, preference) {
    if (typeof window === 'undefined' || !orgSlug) {
        return;
    }

    const key = VALID_KEYS.has(preference.key) ? preference.key : DEFAULT_MEMBER_SORT.key;
    const dir = preference.dir === 'desc' ? 'desc' : 'asc';

    window.localStorage.setItem(
        memberSortStorageKey(orgSlug),
        JSON.stringify({ key, dir }),
    );
}
