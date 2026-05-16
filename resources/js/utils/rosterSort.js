/** @typedef {'asc' | 'desc'} RosterSortDirection */

/** @typedef {'last_name' | 'first_name' | 'email' | 'sector' | 'status' | 'invited' | 'notes'} RosterSortColumn */

/**
 * @typedef {{ column: RosterSortColumn, direction: RosterSortDirection }} RosterSortPreference
 */

export const ROSTER_SORT_COLUMNS = [
    { value: 'last_name', label: 'Last name' },
    { value: 'first_name', label: 'First name' },
    { value: 'email', label: 'Email' },
    { value: 'sector', label: 'Sector' },
    { value: 'status', label: 'Status' },
    { value: 'invited', label: 'Invited' },
    { value: 'included', label: 'Included' },
    { value: 'notes', label: 'Notes' },
];

/** @type {RosterSortPreference} */
export const DEFAULT_ROSTER_SORT = {
    column: 'last_name',
    direction: 'asc',
};

const STORAGE_PREFIX = 'teams-selector:roster-sort:';

const VALID_COLUMNS = new Set(ROSTER_SORT_COLUMNS.map((c) => c.value));

/**
 * @param {string} orgSlug
 */
export function rosterSortStorageKey(orgSlug) {
    return `${STORAGE_PREFIX}${orgSlug}`;
}

/**
 * @param {string} orgSlug
 * @returns {RosterSortPreference}
 */
export function loadRosterSortPreference(orgSlug) {
    if (typeof window === 'undefined' || !orgSlug) {
        return { ...DEFAULT_ROSTER_SORT };
    }

    try {
        const raw = window.localStorage.getItem(rosterSortStorageKey(orgSlug));
        if (!raw) {
            return { ...DEFAULT_ROSTER_SORT };
        }

        const parsed = JSON.parse(raw);
        const column = VALID_COLUMNS.has(parsed?.column) ? parsed.column : DEFAULT_ROSTER_SORT.column;
        const direction = parsed?.direction === 'desc' ? 'desc' : 'asc';

        return { column, direction };
    } catch {
        return { ...DEFAULT_ROSTER_SORT };
    }
}

/**
 * @param {string} orgSlug
 * @param {RosterSortPreference} preference
 */
export function saveRosterSortPreference(orgSlug, preference) {
    if (typeof window === 'undefined' || !orgSlug) {
        return;
    }

    const column = VALID_COLUMNS.has(preference.column) ? preference.column : DEFAULT_ROSTER_SORT.column;
    const direction = preference.direction === 'desc' ? 'desc' : 'asc';

    window.localStorage.setItem(
        rosterSortStorageKey(orgSlug),
        JSON.stringify({ column, direction }),
    );
}

const STATUS_ORDER = { accepted: 0, pending: 1, declined: 2 };

/**
 * @param {Record<string, unknown>} row
 * @param {RosterSortColumn} column
 */
function sortValueForColumn(row, column) {
    switch (column) {
        case 'first_name':
            return String(row.first_name ?? '').trim().toLowerCase();
        case 'email':
            return String(row.email ?? '').trim().toLowerCase();
        case 'sector':
            return String(row.sector?.name ?? '').trim().toLowerCase();
        case 'status':
            return STATUS_ORDER[row.status] ?? 9;
        case 'invited':
            return row.invited ? 1 : 0;
        case 'included':
            return row.included ? 1 : 0;
        case 'notes':
            return String(row.notes ?? '').trim().toLowerCase();
        case 'last_name':
        default:
            return String(row.last_name ?? '').trim().toLowerCase();
    }
}

/**
 * @param {unknown} a
 * @param {unknown} b
 */
function compareValues(a, b) {
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return String(a).localeCompare(String(b), undefined, { sensitivity: 'base', numeric: true });
}

/**
 * @param {Record<string, unknown>[]} rows
 * @param {RosterSortPreference} preference
 */
export function sortRosterRows(rows, preference) {
    const column = VALID_COLUMNS.has(preference.column) ? preference.column : DEFAULT_ROSTER_SORT.column;
    const dir = preference.direction === 'desc' ? -1 : 1;

    return [...rows].sort((a, b) => {
        const primary = compareValues(sortValueForColumn(a, column), sortValueForColumn(b, column)) * dir;
        if (primary !== 0) {
            return primary;
        }

        const last = compareValues(sortValueForColumn(a, 'last_name'), sortValueForColumn(b, 'last_name'));
        if (last !== 0) {
            return last;
        }

        const first = compareValues(sortValueForColumn(a, 'first_name'), sortValueForColumn(b, 'first_name'));
        if (first !== 0) {
            return first;
        }

        return Number(a.id) - Number(b.id);
    });
}
