/**
 * @returns {{ ok: true, value: string } | { ok: false, message: string }}
 */
export function requireTrimmedName(name, label = 'Name') {
    const value = String(name ?? '').trim();

    if (value === '') {
        return { ok: false, message: `${label} is required.` };
    }

    if (value.length > 255) {
        return { ok: false, message: `${label} must be 255 characters or fewer.` };
    }

    return { ok: true, value };
}

/**
 * @returns {{ ok: true, value: number | null } | { ok: false, message: string }}
 */
export function parseOptionalSortOrder(value) {
    if (value === '' || value == null) {
        return { ok: true, value: null };
    }

    const trimmed = String(value).trim();

    if (!/^\d+$/.test(trimmed)) {
        return { ok: false, message: 'Sort order must be a whole number.' };
    }

    const n = Number(trimmed);

    if (n < 0 || n > 65535) {
        return { ok: false, message: 'Sort order must be between 0 and 65535.' };
    }

    return { ok: true, value: n };
}
