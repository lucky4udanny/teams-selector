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

/**
 * @returns {{ ok: true, value: string } | { ok: false, message: string }}
 */
export function requireEventDate(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return { ok: false, message: 'Date is required.' };
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
        return { ok: false, message: 'Choose a valid date.' };
    }

    return { ok: true, value: text };
}

/**
 * @returns {{ ok: true, value: number } | { ok: false, message: string }}
 */
export function requireEventTypeId(value) {
    if (value == null || value === '') {
        return { ok: false, message: 'Event type is required.' };
    }

    return { ok: true, value: Number(value) };
}

/**
 * @param {{ name: string, description?: string, event_date: string, event_type_id: unknown }} fields
 * @returns {{ valid: boolean, errors: Record<string, string>, values: { name: string, event_date: string, event_type_id: number } }}
 */
export function validateEventDetails(fields) {
    const errors = {};

    const name = requireTrimmedName(fields.name, 'Event name');
    if (!name.ok) {
        errors.name = name.message;
    }

    const date = requireEventDate(fields.event_date);
    if (!date.ok) {
        errors.event_date = date.message;
    }

    const type = requireEventTypeId(fields.event_type_id);
    if (!type.ok) {
        errors.event_type_id = type.message;
    }

    const desc = String(fields.description ?? '');
    if (desc.length > 5000) {
        errors.description = 'Description must be 5000 characters or fewer.';
    }

    return {
        valid: Object.keys(errors).length === 0,
        errors,
        values: {
            name: name.ok ? name.value : '',
            event_date: date.ok ? date.value : '',
            event_type_id: type.ok ? type.value : 0,
        },
    };
}

/**
 * @param {import('@inertiajs/vue3').InertiaForm} form
 * @param {Record<string, string>} errors
 */
export function applyFormErrors(form, errors) {
    form.clearErrors();
    for (const [key, message] of Object.entries(errors)) {
        form.setError(key, message);
    }
}

/**
 * @returns {{ ok: true, value: number } | { ok: false, message: string }}
 */
export function requirePositiveInt(value, label, { min = 1, max = 999 } = {}) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return { ok: false, message: `${label} is required.` };
    }

    if (!/^\d+$/.test(trimmed)) {
        return { ok: false, message: `${label} must be a whole number.` };
    }

    const n = Number(trimmed);

    if (n < min || n > max) {
        return { ok: false, message: `${label} must be between ${min} and ${max}.` };
    }

    return { ok: true, value: n };
}

/**
 * @param {{ type: string, scope: string, weight: number, config: Record<string, unknown> }} rule
 * @returns {{ valid: boolean, errors: Record<string, string> }}
 */
export function validateRuleForm(rule) {
    const errors = {};

    if (!rule.type) {
        errors.type = 'Rule type is required.';
    }

    if (!rule.scope) {
        errors.scope = 'Scope is required.';
    }

    const weight = Number(rule.weight);
    if (Number.isNaN(weight) || weight < 0 || weight > 1000000) {
        errors.weight = 'Weight must be between 0 and 1,000,000.';
    }

    const cfg = rule.config || {};

    switch (rule.type) {
        case 'team_size': {
            const size = Number(cfg.size);
            if (!Number.isInteger(size) || size < 1) {
                errors.config = 'Team size must be at least 1.';
            }
            break;
        }
        case 'group_size': {
            const tpg = Number(cfg.teams_per_group);
            if (!Number.isInteger(tpg) || tpg < 1) {
                errors.config = 'Teams per group must be at least 1.';
            }
            break;
        }
        case 'banned_pair':
        case 'preferred_pair': {
            const a = cfg.member_a_id;
            const b = cfg.member_b_id;
            if (a == null || b == null) {
                errors.config = 'Choose two different members for this pair.';
            } else if (Number(a) === Number(b)) {
                errors.config = 'Pair members must be different.';
            }
            break;
        }
        case 'repeat_pair': {
            const prior = Number(cfg.event_id);
            if (!prior || prior < 1) {
                errors.config = 'Choose a prior event for repeat-pair history.';
            }
            break;
        }
        case 'skill_leveling': {
            const min = Number(cfg.min_avg);
            const max = Number(cfg.max_avg);
            if (
                Number.isNaN(min) ||
                Number.isNaN(max) ||
                min < 0 ||
                max < 0 ||
                min > 100 ||
                max > 100 ||
                min > max
            ) {
                errors.config = 'Skill range must be 0–100 with min ≤ max.';
            }
            break;
        }
        default:
            break;
    }

    return { valid: Object.keys(errors).length === 0, errors };
}

/**
 * @param {{ iterations: number }} fields
 * @returns {{ valid: boolean, errors: Record<string, string> }}
 */
export function validateGenerateDraft(fields) {
    const errors = {};
    const iters = Number(fields.iterations);

    if (Number.isNaN(iters) || iters < 100 || iters > 20000) {
        errors.iterations = 'Iterations must be between 100 and 20,000.';
    }

    const name = String(fields.name ?? '').trim();
    if (name.length > 255) {
        errors.name = 'Draft name must be 255 characters or fewer.';
    }

    return { valid: Object.keys(errors).length === 0, errors };
}

/**
 * @param {string[]} names
 * @param {string} [fieldKey]
 * @param {string} [label]
 * @returns {{ valid: boolean, errors: Record<string, string> }}
 */
export function validateDraftNames(names, fieldKey = 'team_names', label = 'Name') {
    for (let i = 0; i < names.length; i++) {
        const n = String(names[i] ?? '');
        if (n.length > 255) {
            return {
                valid: false,
                errors: {
                    [fieldKey]: `${label} for slot ${i + 1} must be 255 characters or fewer.`,
                },
            };
        }
    }

    return { valid: true, errors: {} };
}
