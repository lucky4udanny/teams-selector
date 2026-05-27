/** Badge variant for roster member status (matches summary badges on roster tab). */
export function rosterStatusVariant({ included = true, status = 'pending' } = {}) {
    if (!included) {
        return 'neutral';
    }

    const map = {
        pending: 'warning',
        accepted: 'success',
        declined: 'danger',
    };

    return map[status] ?? 'neutral';
}

const rosterRowClassByVariant = {
    info: 'ts-roster-row-info',
    success: 'ts-roster-row-success',
    warning: 'ts-roster-row-warning',
    danger: 'ts-roster-row-danger',
    neutral: 'ts-roster-row-neutral',
};

/** Row background class for roster tables (same palette as summary badges). */
export function rosterRowClass(row) {
    return rosterRowClassByVariant[rosterStatusVariant(row)] ?? rosterRowClassByVariant.neutral;
}
