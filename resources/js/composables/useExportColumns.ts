import { computed, ref, watch } from 'vue';

export const EXPORT_COLUMNS_STORAGE_KEY = 'ts:exportColumns';

export const exportColumnOptions = [
    { value: 'team_index', label: 'Team #' },
    { value: 'team_name', label: 'Team name' },
    { value: 'group_index', label: 'Group #' },
    { value: 'group_name', label: 'Group name' },
    { value: 'member_id', label: 'Member ID' },
    { value: 'display_name', label: 'Display name' },
    { value: 'first_name', label: 'First name' },
    { value: 'last_name', label: 'Last name' },
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Phone' },
    { value: 'company', label: 'Company' },
    { value: 'sector', label: 'Sector' },
    { value: 'skill', label: 'Skill' },
    { value: 'notes', label: 'Notes' },
] as const;

export type ExportColumnValue = (typeof exportColumnOptions)[number]['value'];

export interface MemberDetail {
    key: string;
    value: string;
    isName?: boolean;
}

export interface OrgMember {
    id: number;
    display_name?: string;
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    company?: string;
    sector?: { id: number; name: string } | null;
    notes?: string;
    skill_level?: number | null;
}

/**
 * Average skill across a set of member IDs. Members without a recorded skill
 * default to 50 (matching the solver's behaviour). Returns null for empty lists.
 */
export function avgSkill(memberIds: number[], memberFullById: Map<number, OrgMember>): number | null {
    if (memberIds.length === 0) return null;
    const sum = memberIds.reduce(
        (acc, id) => acc + (memberFullById.get(id)?.skill_level ?? 50),
        0,
    );
    return Math.round(sum / memberIds.length);
}

export function buildMemberDetails(
    mid: number,
    member: OrgMember | undefined,
    selectedColumns: string[],
    fallbackName?: string,
): MemberDetail[] {
    const raw: MemberDetail[] = [];

    for (const col of selectedColumns) {
        if (col === 'display_name') {
            raw.push({ key: 'display_name', value: member?.display_name || fallbackName || `#${mid}`, isName: true });
        } else if (col === 'first_name' && member?.first_name) {
            raw.push({ key: 'first_name', value: member.first_name, isName: true });
        } else if (col === 'last_name' && member?.last_name) {
            raw.push({ key: 'last_name', value: member.last_name, isName: true });
        } else if (col === 'member_id') {
            raw.push({ key: 'id', value: `#${mid}` });
        } else if (col === 'email' && member?.email) {
            raw.push({ key: 'email', value: member.email });
        } else if (col === 'phone' && member?.phone) {
            raw.push({ key: 'phone', value: member.phone });
        } else if (col === 'company' && member?.company) {
            raw.push({ key: 'company', value: member.company });
        } else if (col === 'sector' && member?.sector?.name) {
            raw.push({ key: 'sector', value: member.sector.name });
        } else if (col === 'skill') {
            raw.push({ key: 'skill', value: `Skill: ${member?.skill_level ?? 50}` });
        } else if (col === 'notes' && member?.notes) {
            raw.push({ key: 'notes', value: member.notes });
        }
    }

    // Merge adjacent first_name + last_name (in either order) onto one line
    const result: MemberDetail[] = [];
    for (let i = 0; i < raw.length; i++) {
        const curr = raw[i];
        const next = raw[i + 1];
        const adjacent =
            (curr.key === 'first_name' && next?.key === 'last_name') ||
            (curr.key === 'last_name' && next?.key === 'first_name');
        if (adjacent) {
            result.push({ key: 'full_name', value: `${curr.value} ${next.value}`, isName: true });
            i++;
        } else {
            result.push(curr);
        }
    }

    return result;
}

export function useExportColumns() {
    const loadStored = (): string[] => {
        try {
            const stored = localStorage.getItem(EXPORT_COLUMNS_STORAGE_KEY);
            if (stored) {
                const parsed: unknown = JSON.parse(stored);
                if (Array.isArray(parsed)) {
                    const valid = parsed.filter((v): v is string =>
                        typeof v === 'string' &&
                        exportColumnOptions.some((o) => o.value === v),
                    );
                    if (valid.length) return valid;
                }
            }
        } catch {
            // ignore storage errors
        }
        return exportColumnOptions.map((c) => c.value);
    };

    const selectedExportColumns = ref<string[]>(loadStored());

    watch(
        selectedExportColumns,
        (cols) => {
            try {
                localStorage.setItem(EXPORT_COLUMNS_STORAGE_KEY, JSON.stringify(cols));
            } catch {
                // ignore storage errors
            }
        },
    );

    const dragColIdx = ref<number | null>(null);

    const exportColumnLabel = (value: string) =>
        exportColumnOptions.find((o) => o.value === value)?.label ?? value;

    const availableExportColumns = computed(() =>
        exportColumnOptions.filter((o) => !selectedExportColumns.value.includes(o.value)),
    );

    const removeExportColumn = (value: string) => {
        selectedExportColumns.value = selectedExportColumns.value.filter((v) => v !== value);
    };

    const addExportColumn = (value: string) => {
        if (!selectedExportColumns.value.includes(value)) {
            selectedExportColumns.value = [...selectedExportColumns.value, value];
        }
    };

    const onColDragStart = (idx: number) => {
        dragColIdx.value = idx;
    };

    const onColDragOver = (idx: number) => {
        if (dragColIdx.value === null || dragColIdx.value === idx) return;
        const cols = [...selectedExportColumns.value];
        const [item] = cols.splice(dragColIdx.value, 1);
        cols.splice(idx, 0, item);
        selectedExportColumns.value = cols;
        dragColIdx.value = idx;
    };

    const onColDragEnd = () => {
        dragColIdx.value = null;
    };

    return {
        selectedExportColumns,
        dragColIdx,
        exportColumnLabel,
        availableExportColumns,
        removeExportColumn,
        addExportColumn,
        onColDragStart,
        onColDragOver,
        onColDragEnd,
    };
}
