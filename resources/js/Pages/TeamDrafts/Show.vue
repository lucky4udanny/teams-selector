<script setup>
import Alert from '@/Components/Alert.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TeamMemberEditor from '@/Components/TeamMemberEditor.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { applyFormErrors, validateDraftNames } from '@/utils/formValidation';
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

const memberName = (id) => memberMap.value.get(id) ?? `Member #${id}`;

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

const editSearchResults = computed(() => {
    const q = editSearch.value.toLowerCase().trim();
    if (!q) return [];
    return (props.orgMembers ?? [])
        .filter((m) => !editMemberIds.value.includes(m.id) && m.display_name.toLowerCase().includes(q))
        .slice(0, 8);
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

const joinNames = (names: string[]): string => {
    if (names.length === 0) return '';
    if (names.length === 1) return names[0];
    if (names.length === 2) return `${names[0]} and ${names[1]}`;

    return names.slice(0, -1).join(', ') + ', and ' + names[names.length - 1];
};

const formatViolation = (v: Violation): string => {
    const mName = (id: unknown) => memberMap.value.get(Number(id)) ?? `Member #${id}`;

    switch (v.type) {
        case 'skill_leveling':
            if (v.avg_skill != null && v.min_avg != null && v.max_avg != null) {
                return `Skill levelling: avg skill ${v.avg_skill} is outside ${v.min_avg}–${v.max_avg}`;
            }

            return String(v.detail ?? 'Skill levelling violation');

        case 'banned_pair':
            if (Array.isArray(v.member_ids) && v.member_ids.length === 2) {
                return `${mName(v.member_ids[0])} and ${mName(v.member_ids[1])} are a banned pair`;
            }

            return 'Banned pair';

        case 'preferred_pair': {
            const scope = v.scope === 'group' ? 'group' : 'team';
            if (Array.isArray(v.member_ids) && v.member_ids.length === 2) {
                return `${mName(v.member_ids[0])} and ${mName(v.member_ids[1])} should be in the same ${scope} but are separated`;
            }

            return `Preferred pair separated (${scope}-level)`;
        }

        case 'repeat_pair':
            if (Array.isArray(v.member_ids) && v.member_ids.length === 2) {
                return `${mName(v.member_ids[0])} and ${mName(v.member_ids[1])} were paired in a prior event`;
            }

            return 'Repeat pair from a prior event';

        case 'member_attribute': {
            const label = String(v.attribute_label ?? v.attribute ?? 'attribute');
            const valueLabel = v.attribute_value_label ? ` "${v.attribute_value_label}"` : '';
            if (Array.isArray(v.offending_member_ids) && v.offending_member_ids.length >= 2) {
                return `${joinNames((v.offending_member_ids as unknown[]).map(mName))} share ${label}${valueLabel}`;
            }
            if (typeof v.distinct_count === 'number') {
                return `Members have ${v.distinct_count} different ${label} values`;
            }

            return `Members share the same ${label}${valueLabel}`;
        }

        default:
            return String(v.detail ?? v.type ?? 'Violation');
    }
};

const showViolationPenalty = (v: Violation): boolean =>
    v.type === 'skill_leveling' ||
    (v.type === 'member_attribute' && Number(v.penalty) !== Number(v.weight));
</script>

<template>
    <Head :title="`Draft — ${event.name}`" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-brand-blue/70">
                        <Link
                            class="font-medium hover:text-brand-navy"
                            :href="
                                `${route('organizations.events.show', {
                                    organization: organization.slug,
                                    event: event.id,
                                })}?tab=drafts`
                            "
                        >
                            ← {{ event.name }}
                        </Link>
                    </p>
                    <h1 class="ts-heading-page mt-1">
                        {{ teamDraft.name || `Draft #${teamDraft.id}` }}
                    </h1>
                    <p class="mt-1 text-sm text-brand-blue/70">
                        Created {{ teamDraft.created_at ? new Date(teamDraft.created_at).toLocaleString() : '—' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div
                        class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                        :class="penaltyHue"
                    >
                        <ProgressRing
                            v-if="totalPenalty !== null"
                            :value="Math.min(totalPenalty, 100)"
                            :max="100"
                            :size="36"
                        />
                        <span class="font-medium text-brand-navy">
                            Penalty:
                            <span v-if="totalPenalty !== null">{{ totalPenalty }}</span>
                            <span v-else>—</span>
                        </span>
                    </div>
                    <span
                        v-if="teamDraft.is_final || event.is_finalized"
                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"
                    >
                        Final
                    </span>
                </div>
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
                    {{ formatViolation(v) }}
                    <span v-if="showViolationPenalty(v)" class="ml-1 text-xs text-amber-700/70"
                        >(score: {{ v.penalty }})</span
                    >
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
                </div>
                <ul
                    v-if="groupViolations.get(gi)?.length"
                    class="mb-4 space-y-1"
                >
                    <li
                        v-for="(v, i) in groupViolations.get(gi)"
                        :key="`gv${gi}_${i}`"
                        class="flex items-start gap-1.5 text-xs text-amber-700"
                    >
                        <span class="mt-0.5 shrink-0 select-none">⚠</span>
                        <span>
                            {{ formatViolation(v) }}<span
                                v-if="showViolationPenalty(v)"
                                class="ml-0.5 text-amber-600/70"
                                > (score: {{ v.penalty }})</span
                            >
                        </span>
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
                            <h4 v-else class="font-semibold text-brand-navy">
                                {{ teamDisplayLabel(ti, teamNamesForm.team_names) }}
                            </h4>
                            <button
                                v-if="canUpdate && editingTeamIndex !== ti"
                                type="button"
                                class="ml-auto shrink-0 text-xs text-brand-blue/50 hover:text-brand-blue"
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
                            <ul class="space-y-1 text-sm text-brand-blue/90">
                                <li v-for="mid in teams[ti]?.member_ids || []" :key="mid">
                                    {{ memberName(mid) }}
                                </li>
                            </ul>
                            <ul
                                v-if="teamViolations.get(ti)?.length"
                                class="mt-3 space-y-1 border-t border-amber-100 pt-3"
                            >
                                <li
                                    v-for="(v, i) in teamViolations.get(ti)"
                                    :key="`tv${ti}_${i}`"
                                    class="flex items-start gap-1.5 text-xs text-amber-700"
                                >
                                    <span class="mt-0.5 shrink-0 select-none">⚠</span>
                                    <span>
                                        {{ formatViolation(v) }}<span
                                            v-if="showViolationPenalty(v)"
                                            class="ml-0.5 text-amber-600/70"
                                            > (score: {{ v.penalty }})</span
                                        >
                                    </span>
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
                    <h4 v-else class="font-semibold text-brand-navy">
                        {{ teamDisplayLabel(ti, teamNamesForm.team_names) }}
                    </h4>
                    <button
                        v-if="canUpdate && editingTeamIndex !== ti"
                        type="button"
                        class="ml-auto shrink-0 text-xs text-brand-blue/50 hover:text-brand-blue"
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
                    <ul class="space-y-1 text-sm text-brand-blue/90">
                        <li v-for="mid in team.member_ids || []" :key="mid">
                            {{ memberName(mid) }}
                        </li>
                    </ul>
                    <ul
                        v-if="teamViolations.get(ti)?.length"
                        class="mt-3 space-y-1 border-t border-amber-100 pt-3"
                    >
                        <li
                            v-for="(v, i) in teamViolations.get(ti)"
                            :key="`tv${ti}_${i}`"
                            class="flex items-start gap-1.5 text-xs text-amber-700"
                        >
                            <span class="mt-0.5 shrink-0 select-none">⚠</span>
                            <span>
                                {{ formatViolation(v) }}<span
                                    v-if="showViolationPenalty(v)"
                                    class="ml-0.5 text-amber-600/70"
                                    > (score: {{ v.penalty }})</span
                                >
                            </span>
                        </li>
                    </ul>
                </template>
            </section>
        </div>

        <div v-if="canUpdate && teams.length" class="mt-8 border-t border-brand-mist pt-6">
            <Alert v-if="namesFormHasErrors" variant="error" class="mb-4" role="alert">
                Please fix name errors before saving.
            </Alert>
            <p v-if="teamNamesForm.errors.team_names" class="mb-2 text-sm text-red-600">
                {{ teamNamesForm.errors.team_names }}
            </p>
            <p v-if="teamNamesForm.errors.group_names" class="mb-2 text-sm text-red-600">
                {{ teamNamesForm.errors.group_names }}
            </p>
            <PrimaryButton type="button" :disabled="teamNamesForm.processing" @click="saveNames">
                {{ teamNamesForm.processing ? 'Saving…' : 'Save names' }}
            </PrimaryButton>
        </div>

        <div
            v-if="canFinalize && !event.is_finalized"
            class="mt-10 flex flex-wrap items-center gap-3 border-t border-brand-mist pt-8"
        >
            <PrimaryButton type="button" :disabled="blockingErrors.length > 0 || finalizeProcessing" @click="showFinalize = true">
                Finalize event with this draft
            </PrimaryButton>
            <p v-if="blockingErrors.length" class="text-sm text-amber-800">
                Resolve blocking errors before finalizing.
            </p>
        </div>

        <div v-if="canUpdate && !teamDraft.is_final" class="mt-6 flex flex-wrap gap-3">
            <SecondaryButton type="button" class="text-red-700 hover:bg-red-50" @click="showDelete = true">
                Delete draft
            </SecondaryButton>
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
