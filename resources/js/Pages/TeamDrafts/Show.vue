<script setup lang="ts">
import Alert from '@/Components/Alert.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TeamMemberEditor from '@/Components/TeamMemberEditor.vue';
import TextInput from '@/Components/TextInput.vue';
import Toggle from '@/Components/Toggle.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import {
    avgSkill,
    buildMemberDetails,
    exportColumnOptions,
    useExportColumns,
} from '@/composables/useExportColumns';
import { applyFormErrors, validateDraftNames } from '@/utils/formValidation';
import { ArrowDownTrayIcon, ArrowLeftIcon, Bars3Icon, ChevronDownIcon, PlusIcon, PrinterIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    organization: Object,
    event: Object,
    teamDraft: Object,
    conflicts: Array,
    orgMembers: Array,
    canUpdate: Boolean,
    canFinalize: Boolean,
});

const state = computed(() =>
    props.teamDraft?.state && typeof props.teamDraft.state === 'object' ? props.teamDraft.state : {},
);

const teams = computed(() => (Array.isArray(state.value.teams) ? state.value.teams : []));
const groups = computed(() => (Array.isArray(state.value.groups) ? state.value.groups : []));
type Violation = Record<string, unknown>;

const violations = computed<Violation[]>(() =>
    Array.isArray(state.value.violations) ? (state.value.violations as Violation[]) : [],
);
const blockingErrors = computed(() =>
    Array.isArray(state.value.blocking_errors) ? state.value.blocking_errors : [],
);
const totalPenalty = computed(() => state.value.total_penalty ?? null);

const memberMap = computed(() => {
    const m = new Map();
    (props.orgMembers || []).forEach((row) => m.set(row.id, row.display_name));

    return m;
});

const memberFullById = computed(() => {
    const m = new Map();
    (props.orgMembers || []).forEach((mem) => m.set(mem.id, mem));
    return m;
});

const memberName = (id) => memberMap.value.get(id) ?? `Member #${id}`;

const {
    selectedExportColumns,
    dragColIdx,
    exportColumnLabel,
    availableExportColumns,
    removeExportColumn,
    addExportColumn,
    onColDragStart,
    onColDragOver,
    onColDragEnd,
} = useExportColumns();

const memberScreenDetails = (mid: number) =>
    buildMemberDetails(mid, memberFullById.value.get(mid), selectedExportColumns.value, memberName(mid));

const handlePrint = () => window.print();

const goBack = () => window.history.back();

const columnsOpen = ref(false);
const showViolations = ref(true);

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    params.set('draft_id', String(props.teamDraft?.id ?? ''));
    if (selectedExportColumns.value.length) {
        params.set('columns', selectedExportColumns.value.join(','));
    }
    params.set('violations', showViolations.value ? '1' : '0');
    return `?${params.toString()}`;
});

const showSkillColumn = computed(() => selectedExportColumns.value.includes('skill'));

const teamAvgSkill = computed(() => {
    if (!showSkillColumn.value) return new Map<number, number | null>();
    const m = new Map<number, number | null>();
    teams.value.forEach((team: { member_ids?: number[] }, ti: number) => {
        m.set(ti, avgSkill(team.member_ids || [], memberFullById.value));
    });
    return m;
});

const groupAvgSkill = computed(() => {
    if (!showSkillColumn.value) return new Map<number, number | null>();
    const m = new Map<number, number | null>();
    groups.value.forEach((group: { team_indices?: number[] }, gi: number) => {
        const memberIds = (group.team_indices || []).flatMap(
            (ti: number) => (teams.value[ti]?.member_ids as number[]) || [],
        );
        m.set(gi, avgSkill(memberIds, memberFullById.value));
    });
    return m;
});

const teamNamesForm = useForm({
    team_names: [],
    group_names: [],
});

const syncNamesFromState = () => {
    const tn = state.value.team_names;
    const gn = state.value.group_names;
    const tc = teams.value.length;
    const gc = groups.value.length;
    teamNamesForm.team_names = Array.from({ length: tc }, (_, i) => tn?.[i] ?? '');
    teamNamesForm.group_names = Array.from({ length: gc }, (_, i) => gn?.[i] ?? '');
};

watch(
    () => props.teamDraft?.state,
    () => syncNamesFromState(),
    { immediate: true, deep: true },
);

const namesFormHasErrors = computed(
    () => Object.keys(teamNamesForm.errors).length > 0 && !teamNamesForm.processing,
);

const saveNames = () => {
    const teamResult = validateDraftNames(teamNamesForm.team_names, 'team_names', 'Team name');
    if (!teamResult.valid) {
        applyFormErrors(teamNamesForm, teamResult.errors);

        return;
    }

    const groupResult = validateDraftNames(teamNamesForm.group_names, 'group_names', 'Group name');
    if (!groupResult.valid) {
        applyFormErrors(teamNamesForm, groupResult.errors);

        return;
    }

    teamNamesForm.patch(
        route('organizations.events.team-drafts.update-names', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        { preserveScroll: true, onSuccess: () => teamNamesForm.clearErrors() },
    );
};

const finalizeProcessing = ref(false);
const showFinalize = ref(false);

const runFinalize = () => {
    if (blockingErrors.value.length > 0) {
        return;
    }

    finalizeProcessing.value = true;
    router.post(
        route('organizations.events.team-drafts.finalize', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                finalizeProcessing.value = false;
                showFinalize.value = false;
            },
        },
    );
};

const showDelete = ref(false);
const deleteProcessing = ref(false);

const runDelete = () => {
    deleteProcessing.value = true;
    router.delete(
        route('organizations.events.team-drafts.destroy', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                deleteProcessing.value = false;
                showDelete.value = false;
            },
        },
    );
};

const indexToLetter = (i) => String.fromCharCode(65 + i);

const teamDisplayLabel = (ti, names) => {
    const num = String(ti + 1);
    const name = String(names?.[ti] ?? '').trim();
    if (name && name !== num) return `${num} · ${name}`;
    return name || num;
};

const groupDisplayLabel = (gi, names) => {
    const letter = indexToLetter(gi);
    const name = String(names?.[gi] ?? '').trim();
    if (name && name !== letter) return `${letter} · ${name}`;
    return name || letter;
};

// ── Team member editing ──────────────────────────────────────────────────────
const editingTeamIndex = ref(null);
const editMemberIds = ref([]);
const editSearch = ref('');
const editSaving = ref(false);

const startEdit = (ti) => {
    editingTeamIndex.value = ti;
    editMemberIds.value = [...(teams.value[ti]?.member_ids ?? [])];
    editSearch.value = '';
};

const cancelEdit = () => {
    editingTeamIndex.value = null;
    editMemberIds.value = [];
    editSearch.value = '';
};

const removeMember = (id) => {
    editMemberIds.value = editMemberIds.value.filter((m) => m !== id);
};

const addMember = (id) => {
    if (!editMemberIds.value.includes(id)) {
        editMemberIds.value = [...editMemberIds.value, id];
    }
    editSearch.value = '';
};

// All member IDs that are currently placed on a team OTHER than the one being edited.
const assignedToOtherTeams = computed<Set<number>>(() => {
    const s = new Set<number>();
    teams.value.forEach((team, idx) => {
        if (idx !== editingTeamIndex.value) {
            (team.member_ids ?? []).forEach((id: number) => s.add(id));
        }
    });
    return s;
});

// Members available to be added to the team being edited:
// not currently in this team and not placed on any other team.
const availableToAdd = computed(() =>
    (props.orgMembers ?? []).filter(
        (m) => !editMemberIds.value.includes(m.id) && !assignedToOtherTeams.value.has(m.id),
    ),
);

const editSearchResults = computed(() => {
    const q = editSearch.value.toLowerCase().trim();
    // When there is no query, return the full available pool (cap at 20 for performance).
    if (!q) return availableToAdd.value.slice(0, 20);
    return availableToAdd.value
        .filter((m) => m.display_name.toLowerCase().includes(q))
        .slice(0, 12);
});

// Total matches before the display cap — used by TeamMemberEditor to show an accurate overflow hint.
const totalMatchCount = computed(() => {
    const q = editSearch.value.toLowerCase().trim();
    if (!q) return availableToAdd.value.length;
    return availableToAdd.value.filter((m) => m.display_name.toLowerCase().includes(q)).length;
});

const saveTeamMembers = (ti) => {
    editSaving.value = true;
    router.patch(
        route('organizations.events.team-drafts.update-team-members', [
            props.organization.slug,
            props.event.id,
            props.teamDraft.id,
        ]),
        { team_index: ti, member_ids: editMemberIds.value },
        {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
            onFinish: () => { editSaving.value = false; },
        },
    );
};

const penaltyHue = computed(() => {
    const p = totalPenalty.value;
    if (p === null || p === undefined) {
        return 'border-brand-mist';
    }
    if (p <= 10) {
        return 'border-emerald-200 bg-emerald-50/60';
    }
    if (p <= 50) {
        return 'border-amber-200 bg-amber-50/60';
    }

    return 'border-red-200 bg-red-50/50';
});

// ── Violation helpers ────────────────────────────────────────────────────────

const teamViolations = computed(() => {
    const m = new Map<number, Violation[]>();
    violations.value.forEach((v) => {
        if (typeof v.team_index === 'number') {
            const arr = m.get(v.team_index) ?? [];
            arr.push(v);
            m.set(v.team_index, arr);
        }
    });

    return m;
});

const groupViolations = computed(() => {
    const m = new Map<number, Violation[]>();
    violations.value.forEach((v) => {
        if (typeof v.group_index === 'number') {
            const arr = m.get(v.group_index) ?? [];
            arr.push(v);
            m.set(v.group_index, arr);
        }
    });

    return m;
});

const globalViolations = computed(() =>
    violations.value.filter(
        (v) => typeof v.team_index !== 'number' && typeof v.group_index !== 'number',
    ),
);

const violationText = (v: Violation): string =>
    String(v.formatted ?? v.detail ?? v.type ?? 'Violation');
</script>

<template>
    <Head :title="`Draft — ${event.name}`" />

    <OrganizationLayout :organization="organization">
        <!-- ── Sticky draft context bar ── -->
        <template #subnav>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="cursor-pointer rounded-lg p-1.5 text-brand-blue/60 hover:bg-brand-mist/60 hover:text-brand-navy active:bg-brand-mist"
                    :title="`Back to ${event.name}`"
                    @click="goBack"
                >
                    <ArrowLeftIcon class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-base font-semibold text-brand-navy">
                            {{ teamDraft.name || `Draft #${teamDraft.id}` }}
                        </h1>
                        <span
                            v-if="teamDraft.is_final"
                            class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800"
                        >
                            Final
                        </span>
                    </div>
                    <p class="text-xs text-brand-blue/50">
                        {{ event.name }} ·
                        Created {{ teamDraft.created_at ? new Date(teamDraft.created_at).toLocaleString() : '—' }}
                    </p>
                </div>

                <div
                    class="shrink-0 rounded-lg border px-3 py-1.5 text-sm font-medium text-brand-navy"
                    :class="penaltyHue"
                >
                    Penalty: <span>{{ totalPenalty ?? '—' }}</span>
                </div>

                <button
                    type="button"
                    class="cursor-pointer rounded-lg p-1.5 text-brand-blue/60 hover:bg-brand-mist/60 hover:text-brand-navy active:bg-brand-mist"
                    title="Close"
                    @click="goBack"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>
        </template>

        <Alert v-if="blockingErrors.length" variant="error" class="mb-6">
            <p class="font-semibold">Blocking errors</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li v-for="(err, i) in blockingErrors" :key="`b${i}`">{{ err }}</li>
            </ul>
        </Alert>

        <Alert v-else-if="violations.length" variant="warning" class="mb-6">
            <p class="font-semibold">
                {{ violations.length }} violation{{ violations.length === 1 ? '' : 's' }}
            </p>
            <ul v-if="globalViolations.length" class="mt-2 list-inside list-disc space-y-1 text-sm">
                <li v-for="(v, i) in globalViolations" :key="`gv${i}`">
                    {{ violationText(v) }}
                </li>
            </ul>
            <p
                v-if="violations.length > globalViolations.length"
                class="mt-2 text-xs text-amber-700/70"
            >
                See individual team and group panels below for details.
            </p>
        </Alert>

        <Alert v-if="conflicts?.length" variant="info" class="mb-6">
            <p class="font-semibold">{{ conflicts.length }} planning note(s)</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                <li v-for="(c, i) in conflicts" :key="`c${i}`">{{ typeof c === 'string' ? c : JSON.stringify(c) }}</li>
            </ul>
        </Alert>

        <!-- Column selection and actions -->
        <div class="mb-6 rounded-xl border border-brand-mist bg-white p-4 shadow-sm">
            <!-- Collapsible columns accordion -->
            <div class="mb-4 overflow-hidden rounded-lg border border-brand-mist">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between bg-brand-mist/40 px-4 py-3 text-sm font-semibold text-brand-navy transition-colors hover:bg-brand-mist/70 active:bg-brand-mist"
                    @click="columnsOpen = !columnsOpen"
                >
                    <span>Columns</span>
                    <ChevronDownIcon
                        class="h-4 w-4 text-brand-blue/50 transition-transform duration-200"
                        :class="{ 'rotate-180': columnsOpen }"
                    />
                </button>

                <div v-show="columnsOpen" class="space-y-3 border-t border-brand-mist p-4">
                <!-- Selected columns — drag to reorder -->
                <div class="flex min-h-[48px] flex-wrap gap-2 rounded-lg border border-brand-mist bg-brand-mist/20 p-3">
                    <p v-if="!selectedExportColumns.length" class="text-sm italic text-brand-blue/50">
                        No columns selected.
                    </p>
                    <div
                        v-for="(col, idx) in selectedExportColumns"
                        :key="col"
                        draggable="true"
                        class="inline-flex cursor-grab select-none items-center gap-1 rounded-md bg-brand-blue/10 px-2 py-1 text-xs font-medium text-brand-navy transition-opacity"
                        :class="{ 'opacity-40': dragColIdx === idx }"
                        @dragstart="onColDragStart(idx)"
                        @dragover.prevent="onColDragOver(idx)"
                        @dragend="onColDragEnd"
                    >
                        <Bars3Icon class="h-3.5 w-3.5 shrink-0 text-brand-blue/40" />
                        {{ exportColumnLabel(col) }}
                        <button
                            type="button"
                            class="ml-1 cursor-pointer text-brand-blue/50 hover:text-brand-navy active:opacity-60"
                            @click.stop="removeExportColumn(col)"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Available columns to add -->
                <div v-if="availableExportColumns.length">
                    <span class="mb-1.5 block text-xs text-brand-blue/60">Add columns</span>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="col in availableExportColumns"
                            :key="col.value"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-brand-mist bg-white px-2 py-1 text-xs font-medium text-brand-blue/70 transition-colors hover:border-brand-blue/30 hover:text-brand-navy active:bg-brand-mist/40"
                            @click="addExportColumn(col.value)"
                        >
                            <PlusIcon class="h-3 w-3" />
                            {{ col.label }}
                        </button>
                    </div>
                </div>
            </div>
            </div><!-- end accordion wrapper -->

            <!-- Actions: print icon button + violations toggle -->
            <div class="flex flex-wrap items-center gap-3 border-t border-brand-mist pt-4">
                <button
                    type="button"
                    title="Print"
                    aria-label="Print"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-brand-mist bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-brand-mist/30 active:bg-brand-mist/60"
                    @click="handlePrint"
                >
                    <PrinterIcon class="h-4 w-4" />
                    Print
                </button>

                <a
                    :href="route('organizations.events.export.csv', [props.organization.slug, props.event.id]) + exportQuery"
                    title="Download CSV"
                    aria-label="Download CSV"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-brand-mist bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-brand-mist/30 active:bg-brand-mist/60"
                >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    CSV
                </a>

                <a
                    :href="route('organizations.events.export.xlsx', [props.organization.slug, props.event.id]) + exportQuery"
                    title="Download Excel"
                    aria-label="Download Excel"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-brand-mist bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-brand-mist/30 active:bg-brand-mist/60"
                >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    Excel
                </a>

                <Toggle v-model="showViolations" label="Show violations" class="ml-2" />
            </div>
        </div>

        <!-- Grouped view -->
        <div v-if="groups.length" class="space-y-10">
            <section v-for="(group, gi) in groups" :key="`grp${gi}`">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-brand-blue/40">Group</span>
                    <TextInput
                        v-if="canUpdate"
                        v-model="teamNamesForm.group_names[gi]"
                        :placeholder="indexToLetter(gi)"
                        class="w-36 text-sm font-semibold"
                    />
                    <span v-else class="text-sm font-semibold text-brand-navy">
                        {{ groupDisplayLabel(gi, teamNamesForm.group_names) }}
                    </span>
                    <span
                        v-if="showSkillColumn && groupAvgSkill.get(gi) != null"
                        class="text-xs text-brand-blue/50"
                    >Avg skill: {{ groupAvgSkill.get(gi) }}</span>
                </div>
                <ul
                    v-if="showViolations && groupViolations.get(gi)?.length"
                    class="mb-4 space-y-1"
                >
                    <li
                        v-for="(v, i) in groupViolations.get(gi)"
                        :key="`gv${gi}_${i}`"
                        class="flex items-start gap-1.5 text-xs text-amber-700"
                    >
                        <span class="mt-0.5 shrink-0 select-none">⚠</span>
                        <span>{{ violationText(v) }}</span>
                    </li>
                </ul>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="ti in group.team_indices || []"
                        :key="`team${ti}`"
                        class="rounded-xl border bg-white p-4 shadow-sm"
                        :class="editingTeamIndex === ti ? 'border-brand-blue/30' : 'border-brand-mist'"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <span class="shrink-0 text-xs font-medium text-brand-blue/40">{{ ti + 1 }}</span>
                            <TextInput
                                v-if="canUpdate"
                                v-model="teamNamesForm.team_names[ti]"
                                :placeholder="String(ti + 1)"
                                class="flex-1 text-sm font-semibold"
                            />
                            <template v-else>
                                <h4 class="font-semibold text-brand-navy">
                                    {{ teamDisplayLabel(ti, teamNamesForm.team_names) }}
                                </h4>
                                <span
                                    v-if="showSkillColumn && teamAvgSkill.get(ti) != null"
                                    class="text-xs text-brand-blue/50"
                                >Avg skill: {{ teamAvgSkill.get(ti) }}</span>
                            </template>
                            <button
                                v-if="canUpdate && editingTeamIndex !== ti"
                                type="button"
                                class="ml-auto shrink-0 cursor-pointer text-xs text-brand-blue/50 hover:text-brand-blue active:opacity-70"
                                @click="startEdit(ti)"
                            >
                                Edit members
                            </button>
                        </div>
                        <template v-if="editingTeamIndex === ti">
                            <TeamMemberEditor
                                :edit-member-ids="editMemberIds"
                                :edit-search="editSearch"
                                :edit-search-results="editSearchResults"
                                :available-count="availableToAdd.length"
                                :match-count="totalMatchCount"
                                :edit-saving="editSaving"
                                :member-name="memberName"
                                @update:edit-search="editSearch = $event"
                                @remove="removeMember"
                                @add="addMember"
                                @save="saveTeamMembers(ti)"
                                @cancel="cancelEdit"
                            />
                        </template>
                        <template v-else>
                            <ul class="space-y-1">
                                <li
                                    v-for="mid in teams[ti]?.member_ids || []"
                                    :key="mid"
                                    class="rounded-lg bg-brand-blue/5 px-2.5 py-2"
                                >
                                    <template v-for="d in memberScreenDetails(mid)" :key="d.key">
                                        <p v-if="d.isName" class="text-sm font-medium leading-snug text-brand-navy">{{ d.value }}</p>
                                        <span v-else class="block text-xs text-brand-blue/60">{{ d.value }}</span>
                                    </template>
                                </li>
                            </ul>
                            <ul
                                v-if="showViolations && teamViolations.get(ti)?.length"
                                class="mt-3 space-y-1 border-t border-amber-100 pt-3"
                            >
                                <li
                                    v-for="(v, i) in teamViolations.get(ti)"
                                    :key="`tv${ti}_${i}`"
                                    class="flex items-start gap-1.5 text-xs text-amber-700"
                                >
                                    <span class="mt-0.5 shrink-0 select-none">⚠</span>
                                    <span>{{ violationText(v) }}</span>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- Flat view (no groups) -->
        <div v-else class="grid gap-6 lg:grid-cols-2">
            <section
                v-for="(team, ti) in teams"
                :key="`team${ti}`"
                class="rounded-xl border bg-white p-4 shadow-sm"
                :class="editingTeamIndex === ti ? 'border-brand-blue/30' : 'border-brand-mist'"
            >
                <div class="mb-3 flex items-center gap-2">
                    <span class="shrink-0 text-xs font-medium text-brand-blue/40">{{ ti + 1 }}</span>
                    <TextInput
                        v-if="canUpdate"
                        v-model="teamNamesForm.team_names[ti]"
                        :placeholder="String(ti + 1)"
                        class="flex-1 text-sm font-semibold"
                    />
                    <template v-else>
                        <h4 class="font-semibold text-brand-navy">
                            {{ teamDisplayLabel(ti, teamNamesForm.team_names) }}
                        </h4>
                        <span
                            v-if="showSkillColumn && teamAvgSkill.get(ti) != null"
                            class="text-xs text-brand-blue/50"
                        >Avg skill: {{ teamAvgSkill.get(ti) }}</span>
                    </template>
                    <button
                        v-if="canUpdate && editingTeamIndex !== ti"
                        type="button"
                        class="ml-auto shrink-0 cursor-pointer text-xs text-brand-blue/50 hover:text-brand-blue active:opacity-70"
                        @click="startEdit(ti)"
                    >
                        Edit members
                    </button>
                </div>
                <template v-if="editingTeamIndex === ti">
                    <TeamMemberEditor
                        :edit-member-ids="editMemberIds"
                        :edit-search="editSearch"
                        :edit-search-results="editSearchResults"
                        :available-count="availableToAdd.length"
                        :match-count="totalMatchCount"
                        :edit-saving="editSaving"
                        :member-name="memberName"
                        @update:edit-search="editSearch = $event"
                        @remove="removeMember"
                        @add="addMember"
                        @save="saveTeamMembers(ti)"
                        @cancel="cancelEdit"
                    />
                </template>
                <template v-else>
                    <ul class="space-y-1">
                        <li
                            v-for="mid in team.member_ids || []"
                            :key="mid"
                            class="rounded-lg bg-brand-blue/5 px-2.5 py-2"
                        >
                            <template v-for="d in memberScreenDetails(mid)" :key="d.key">
                                <p v-if="d.isName" class="text-sm font-medium leading-snug text-brand-navy">{{ d.value }}</p>
                                <span v-else class="block text-xs text-brand-blue/60">{{ d.value }}</span>
                            </template>
                        </li>
                    </ul>
                    <ul
                        v-if="showViolations && teamViolations.get(ti)?.length"
                        class="mt-3 space-y-1 border-t border-amber-100 pt-3"
                    >
                        <li
                            v-for="(v, i) in teamViolations.get(ti)"
                            :key="`tv${ti}_${i}`"
                            class="flex items-start gap-1.5 text-xs text-amber-700"
                        >
                            <span class="mt-0.5 shrink-0 select-none">⚠</span>
                            <span>{{ violationText(v) }}</span>
                        </li>
                    </ul>
                </template>
            </section>
        </div>

        <div v-if="canUpdate && teams.length" class="mt-8 border-t border-brand-mist pt-6">
            <Alert v-if="namesFormHasErrors" variant="error" class="mb-3" role="alert">
                Please fix name errors before saving.
            </Alert>
            <p v-if="teamNamesForm.errors.team_names" class="mb-2 text-sm text-red-600">
                {{ teamNamesForm.errors.team_names }}
            </p>
            <p v-if="teamNamesForm.errors.group_names" class="mb-2 text-sm text-red-600">
                {{ teamNamesForm.errors.group_names }}
            </p>
            <!-- All draft actions in one responsive row -->
            <div class="flex flex-wrap items-center gap-3">
                <PrimaryButton type="button" :disabled="teamNamesForm.processing" @click="saveNames">
                    {{ teamNamesForm.processing ? 'Saving…' : 'Save names' }}
                </PrimaryButton>

                <template v-if="canFinalize && !event.is_finalized">
                    <PrimaryButton
                        type="button"
                        :disabled="blockingErrors.length > 0 || finalizeProcessing"
                        @click="showFinalize = true"
                    >
                        Make these teams final
                    </PrimaryButton>
                    <p v-if="blockingErrors.length" class="text-sm text-amber-800">
                        Resolve blocking errors before finalizing.
                    </p>
                </template>

                <DangerButton
                    v-if="canUpdate && !teamDraft.is_final"
                    type="button"
                    @click="showDelete = true"
                >
                    Delete draft
                </DangerButton>
            </div>
        </div>

        <ConfirmDialog
            :show="showFinalize"
            title="Finalize this event?"
            message="This locks the final team assignment for the event using this draft."
            confirm-label="Finalize"
            :processing="finalizeProcessing"
            @close="showFinalize = false"
            @confirm="runFinalize"
        />

        <ConfirmDialog
            :show="showDelete"
            title="Delete this draft?"
            message="Non-final drafts can be regenerated from the event."
            confirm-label="Delete draft"
            :processing="deleteProcessing"
            @close="showDelete = false"
            @confirm="runDelete"
        />
    </OrganizationLayout>
</template>
