<script setup>
import Alert from '@/Components/Alert.vue';
import Badge from '@/Components/Badge.vue';
import ComboboxInput from '@/Components/ComboboxInput.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DateInput from '@/Components/DateInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ListboxInput from '@/Components/ListboxInput.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import RosterMemberPicker from '@/Components/RosterMemberPicker.vue';
import RangeSlider from '@/Components/RangeSlider.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import Toggle from '@/Components/Toggle.vue';
import WeightSlider from '@/Components/WeightSlider.vue';
import DangerButton from '@/Components/DangerButton.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { ArrowDownTrayIcon, Bars3Icon, ChevronDownIcon, PlusIcon, PrinterIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import {
    applyFormErrors,
    validateEventDetails,
    validateGenerateDraft,
    validateRuleForm,
} from '@/utils/formValidation';
import {
    loadRosterSortPreference,
    saveRosterSortPreference,
    sortRosterRows,
} from '@/utils/rosterSort';
import {
    avgSkill,
    buildMemberDetails,
    exportColumnOptions,
    useExportColumns,
} from '@/composables/useExportColumns';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const props = defineProps({
    organization: Object,
    tab: String,
    canManage: Boolean,
    canFinalize: Boolean,
    canRevertFinal: Boolean,
    event: Object,
    orgMembers: Array,
    ruleTypes: Array,
    ruleScopes: Array,
    eventTypes: Array,
    finalizedEvents: Array,
    copySourceEvents: Array,
    roster: Array,
    rules: Array,
    team_drafts: Array,
    final_draft: Object,
});

const slug = computed(() => props.organization.slug);
const eventId = computed(() => props.event.id);

const eventHref = (t) =>
    `${route('organizations.events.show', { organization: slug.value, event: eventId.value })}?tab=${t}`;

const ruleTypeLabels = {
    size: 'Size',
    banned_pair: 'Banned pair',
    preferred_pair: 'Preferred pair',
    repeat_pair: 'Avoid same members (prior event)',
    skill_leveling: 'Skill leveling',
    member_attribute: 'Member attribute',
};

const scopeLabels = {
    team: 'Team',
    group: 'Group',
};

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

const handlePrint = async () => {
    let html;
    try {
        const res = await fetch(printExportUrl.value, { credentials: 'same-origin' });
        if (!res.ok) return;
        html = await res.text();
    } catch {
        return;
    }

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:0;';
    document.body.appendChild(iframe);

    iframe.contentDocument.open();
    iframe.contentDocument.write(html);
    iframe.contentDocument.close();

    // Small delay so inline styles are applied before the print dialog opens
    setTimeout(() => {
        iframe.contentWindow.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) document.body.removeChild(iframe);
        }, 500);
    }, 100);
};

const exportQuery = computed(() => {
    const cols = selectedExportColumns.value;
    if (!cols.length) return '';
    return `?columns=${encodeURIComponent(cols.join(','))}`;
});

const printExportUrl = computed(
    () =>
        `${route('organizations.events.export.print', [slug.value, eventId.value])}${exportQuery.value}`,
);
const csvExportUrl = computed(
    () =>
        `${route('organizations.events.export.csv', [slug.value, eventId.value])}${exportQuery.value}`,
);
const xlsxExportUrl = computed(
    () =>
        `${route('organizations.events.export.xlsx', [slug.value, eventId.value])}${exportQuery.value}`,
);

/* ——— Details ——— */
const detailsForm = useForm({
    name: props.event.name,
    description: props.event.description || '',
    event_date: props.event.event_date,
    event_type_id: props.event.event_type?.id ?? null,
    uses_groups: props.event.uses_groups,
    previous_event_ids: [...(props.event.previous_event_ids || [])],
});

watch(
    () => props.event,
    (e) => {
        detailsForm.name = e.name;
        detailsForm.description = e.description || '';
        detailsForm.event_date = e.event_date;
        detailsForm.event_type_id = e.event_type?.id ?? null;
        detailsForm.uses_groups = e.uses_groups;
        detailsForm.previous_event_ids = [...(e.previous_event_ids || [])];
    },
    { deep: true },
);

const eventTypeOptions = computed(() =>
    (props.eventTypes || []).map((t) => ({ value: t.id, label: t.name })),
);

const priorEventCombobox = computed(() =>
    (props.finalizedEvents || []).map((e) => ({
        id: e.id,
        label: e.label,
    })),
);

const focusDetailsField = async (errors) => {
    const idByKey = {
        name: 'ev_name',
        event_date: 'ev_date',
        description: 'ev_desc',
    };
    const key = Object.keys(errors)[0];
    if (!key) {
        return;
    }
    await nextTick();
    document.getElementById(idByKey[key] ?? 'ev_name')?.focus();
};

const detailsFormHasErrors = computed(
    () => Object.keys(detailsForm.errors).length > 0 && !detailsForm.processing,
);

const saveDetails = () => {
    const result = validateEventDetails(detailsForm.data());
    if (!result.valid) {
        applyFormErrors(detailsForm, result.errors);
        focusDetailsField(result.errors);

        return;
    }

    detailsForm.name = result.values.name;
    detailsForm.event_date = result.values.event_date;
    detailsForm.event_type_id = result.values.event_type_id;

    detailsForm.patch(route('organizations.events.update', [slug.value, eventId.value]), {
        preserveScroll: true,
        onError: () => focusDetailsField(detailsForm.errors),
    });
};

const dupForm = useForm({ name: '' });
const showDup = ref(false);
const submitDup = () => {
    dupForm.clearErrors();
    const name = String(dupForm.name ?? '').trim();
    if (name.length > 255) {
        dupForm.setError('name', 'Name must be 255 characters or fewer.');

        return;
    }
    dupForm.name = name;
    dupForm.post(route('organizations.events.duplicate', [slug.value, eventId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            showDup.value = false;
            dupForm.reset();
        },
    });
};

const showDeleteEvent = ref(false);
const deleteProcessing = ref(false);
const runDeleteEvent = () => {
    deleteProcessing.value = true;
    router.delete(route('organizations.events.destroy', [slug.value, eventId.value]), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            showDeleteEvent.value = false;
        },
    });
};

/* ——— Roster ——— */
const cloneRosterRows = (roster) => (roster || []).map((row) => ({ ...row }));

const rosterState = ref(cloneRosterRows(props.roster));

watch(
    () => props.roster,
    (roster) => {
        rosterState.value = cloneRosterRows(roster);
    },
    { deep: true },
);

const rosterRows = computed(() => rosterState.value || []);
const rsvp = computed(() => props.event.rsvp_counts || {});

const rosterSort = ref(loadRosterSortPreference(props.organization.slug));

watch(
    () => props.organization.slug,
    (orgSlug) => {
        rosterSort.value = loadRosterSortPreference(orgSlug);
    },
);

watch(
    rosterSort,
    (pref) => {
        saveRosterSortPreference(slug.value, pref);
    },
    { deep: true },
);

const sortedRosterRows = computed(() => sortRosterRows(rosterRows.value, rosterSort.value));

const rosterIncluded = computed(() => sortedRosterRows.value.filter((r) => r.included));
const rosterWaiting = computed(() => sortedRosterRows.value.filter((r) => !r.included));

const setRosterSort = (column) => {
    if (rosterSort.value.column === column) {
        rosterSort.value = {
            column,
            direction: rosterSort.value.direction === 'asc' ? 'desc' : 'asc',
        };
    } else {
        rosterSort.value = { column, direction: 'asc' };
    }
};

const rosterMemberIds = computed(() => new Set(rosterRows.value.map((r) => r.member_id)));

const availableMembersToAdd = computed(() =>
    (props.orgMembers || []).filter((m) => !rosterMemberIds.value.has(m.id)),
);

const copyEventOptions = computed(() =>
    (props.copySourceEvents || []).map((e) => ({
        value: e.id,
        label: e.label,
    })),
);

const selectedMemberIdsToAdd = ref([]);
const addMembersProcessing = ref(false);
const copyFromId = ref(null);
const rosterNotice = ref(null);
const draftsNotice = ref(null);

const showAddMembersModal = ref(false);
const showCopyModal = ref(false);

const openAddMembersModal = () => {
    selectedMemberIdsToAdd.value = [];
    showAddMembersModal.value = true;
};

const copyModalNotice = ref(null);

const openCopyModal = () => {
    copyFromId.value = null;
    copyModalNotice.value = null;
    showCopyModal.value = true;
};

const postAddMembers = () => {
    rosterNotice.value = null;
    if (!selectedMemberIdsToAdd.value?.length) {
        rosterNotice.value = {
            variant: 'warning',
            message: 'Select at least one member to add to the roster.',
        };

        return;
    }
    addMembersProcessing.value = true;
    router.post(
        route('organizations.events.members.store', [slug.value, eventId.value]),
        { member_ids: selectedMemberIdsToAdd.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedMemberIdsToAdd.value = [];
                showAddMembersModal.value = false;
            },
            onFinish: () => {
                addMembersProcessing.value = false;
            },
        },
    );
};

const postCopy = () => {
    copyModalNotice.value = null;
    rosterNotice.value = null;
    if (!copyFromId.value) {
        const notice = { variant: 'warning', message: 'Choose an event to copy members from.' };
        if (showCopyModal.value) {
            copyModalNotice.value = notice;
        } else {
            rosterNotice.value = notice;
        }

        return;
    }
    router.post(
        route('organizations.events.members.copy-from-event', [slug.value, eventId.value]),
        { source_event_id: copyFromId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                copyFromId.value = null;
                showCopyModal.value = false;
            },
        },
    );
};

const patchMember = (em, data) => {
    const row = rosterState.value?.find((r) => r.id === em.id);
    const snapshot = row ? { ...row } : null;

    if (row) {
        Object.assign(row, data);
        if (Object.prototype.hasOwnProperty.call(data, 'invited')) {
            row.invited = Boolean(data.invited);
            row.invited_at = data.invited ? row.invited_at ?? new Date().toISOString() : null;
        }
    }

    router.patch(route('organizations.events.members.update', [slug.value, eventId.value, em.id]), data, {
        preserveScroll: true,
        only: ['roster', 'event'],
        onError: () => {
            if (snapshot && rosterState.value) {
                const target = rosterState.value.find((r) => r.id === em.id);
                if (target) {
                    Object.assign(target, snapshot);
                }
            }
        },
    });
};

const selectedEmIds = ref(new Set());
const toggleSelect = (id, checked) => {
    const next = new Set(selectedEmIds.value);
    if (checked) {
        next.add(id);
    } else {
        next.delete(id);
    }
    selectedEmIds.value = next;
};

const allIncludedSelected = computed(
    () =>
        rosterIncluded.value.length > 0 &&
        rosterIncluded.value.every((r) => selectedEmIds.value.has(r.id)),
);
const someIncludedSelected = computed(
    () =>
        !allIncludedSelected.value &&
        rosterIncluded.value.some((r) => selectedEmIds.value.has(r.id)),
);

const allWaitingSelected = computed(
    () =>
        rosterWaiting.value.length > 0 &&
        rosterWaiting.value.every((r) => selectedEmIds.value.has(r.id)),
);
const someWaitingSelected = computed(
    () =>
        !allWaitingSelected.value &&
        rosterWaiting.value.some((r) => selectedEmIds.value.has(r.id)),
);

const toggleSelectAll = (rows, selectAll) => {
    const next = new Set(selectedEmIds.value);
    rows.forEach((r) => (selectAll ? next.add(r.id) : next.delete(r.id)));
    selectedEmIds.value = next;
};

const bulkPatch = (payload) => {
    rosterNotice.value = null;
    const ids = [...selectedEmIds.value];
    if (!ids.length) {
        rosterNotice.value = {
            variant: 'warning',
            message: 'Select roster rows using the checkboxes first.',
        };

        return;
    }
    const items = ids.map((id) => ({ id, ...payload }));
    router.patch(
        route('organizations.events.members.bulk-update', [slug.value, eventId.value]),
        { items },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedEmIds.value = new Set();
            },
        },
    );
};

const localNotes = reactive({});

watch(
    () => props.roster,
    (rows) => {
        (rows || []).forEach((r) => {
            localNotes[r.id] = r.notes ?? '';
        });
    },
    { immediate: true, deep: true },
);

/* ——— Rules ——— */
const scopeOptionsFiltered = computed(() => {
    const scopes = props.ruleScopes || [];
    if (!props.event.uses_groups) {
        return scopes
            .filter((s) => s === 'team')
            .map((s) => ({ value: s, label: scopeLabels[s] || s }));
    }

    return scopes.map((s) => ({ value: s, label: scopeLabels[s] || s }));
});

const typeOptions = computed(() =>
    (props.ruleTypes || []).map((t) => ({
        value: t,
        label: ruleTypeLabels[t] || t,
    })),
);


const ruleModalOpen = ref(false);
const editingRuleId = ref(null);

const defaultConfigForType = (type) => {
    switch (type) {
        case 'size':
            return { size: 4 };
        case 'banned_pair':
        case 'preferred_pair':
            return { member_a_id: null, member_b_id: null };
        case 'repeat_pair':
            return { event_id: (props.finalizedEvents || [])[0]?.id ?? null };
        case 'skill_leveling':
            return { min_avg: 35, max_avg: 65 };
        case 'member_attribute':
            return { attribute: 'sector_id', match: 'same' };
        default:
            return {};
    }
};

const ruleForm = useForm({
    type: 'size',
    scope: 'team',
    weight: 50,
    config: defaultConfigForType('size'),
});

const memberPairOptions = computed(() =>
    (props.orgMembers || []).map((m) => ({
        value: m.id,
        label: m.display_name,
    })),
);

const repeatPriorOptions = computed(() =>
    (props.finalizedEvents || []).map((e) => ({
        value: e.id,
        label: e.label,
    })),
);

const openAddRule = () => {
    editingRuleId.value = null;
    ruleForm.clearErrors();
    ruleForm.type = 'size';
    ruleForm.scope = 'team';
    ruleForm.weight = 50;
    ruleForm.config = defaultConfigForType('size');
    ruleModalOpen.value = true;
};

const openEditRule = (r) => {
    editingRuleId.value = r.id;
    ruleForm.clearErrors();
    ruleForm.type = r.type;
    ruleForm.scope = r.scope;
    ruleForm.weight = r.weight;
    ruleForm.config = { ...r.config };
    ruleModalOpen.value = true;
};

watch(
    () => ruleForm.type,
    (t) => {
        if (editingRuleId.value) {
            return;
        }
        ruleForm.config = defaultConfigForType(t);
    },
);

const ruleFormHasErrors = computed(
    () => Object.keys(ruleForm.errors).length > 0 && !ruleForm.processing,
);

const submitRule = () => {
    const result = validateRuleForm({
        type: ruleForm.type,
        scope: ruleForm.scope,
        weight: ruleForm.weight,
        config: ruleForm.config,
    });
    if (!result.valid) {
        applyFormErrors(ruleForm, result.errors);

        return;
    }

    if (editingRuleId.value) {
        ruleForm.patch(
            route('organizations.events.rules.update', [
                slug.value,
                eventId.value,
                editingRuleId.value,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    ruleModalOpen.value = false;
                    ruleForm.clearErrors();
                },
            },
        );
    } else {
        ruleForm.post(route('organizations.events.rules.store', [slug.value, eventId.value]), {
            preserveScroll: true,
            onSuccess: () => {
                ruleModalOpen.value = false;
                ruleForm.clearErrors();
            },
        });
    }
};

const deleteRule = (id) => {
    router.delete(
        route('organizations.events.rules.destroy', [slug.value, eventId.value, id]),
        { preserveScroll: true },
    );
};

/* ——— Drafts ——— */
const genForm = useForm({
    name: '',
    include_pending: false,
    iterations: 4000,
});

const genFormHasErrors = computed(
    () => Object.keys(genForm.errors).length > 0 && !genForm.processing,
);

const submitGenerate = () => {
    draftsNotice.value = null;
    const result = validateGenerateDraft(genForm.data());
    if (!result.valid) {
        applyFormErrors(genForm, result.errors);

        return;
    }

    genForm.post(route('organizations.events.team-drafts.generate', [slug.value, eventId.value]), {
        preserveScroll: true,
        onSuccess: () => genForm.reset('name'),
    });
};

const draftToFinalize = ref(null);

const requestFinalizeDraft = (draft) => {
    draftsNotice.value = null;
    const blocking = draft.blocking_errors ?? [];
    if (Array.isArray(blocking) && blocking.length > 0) {
        draftsNotice.value = {
            variant: 'error',
            message: `Cannot finalize: ${blocking.join('; ')}`,
        };

        return;
    }
    draftToFinalize.value = draft.id;
};

const confirmFinalizeDraft = () => {
    const id = draftToFinalize.value;
    if (!id) {
        return;
    }
    finalizeDraft(id);
    draftToFinalize.value = null;
};

const conflictsPreview = ref(null);
const conflictsLoading = ref(false);

const loadConflicts = async () => {
    conflictsLoading.value = true;
    conflictsPreview.value = null;
    try {
        const { data } = await window.axios.get(
            route('organizations.events.team-drafts.conflicts', [slug.value, eventId.value]),
        );
        conflictsPreview.value = data.conflicts || [];
    } catch {
        conflictsPreview.value = [];
    } finally {
        conflictsLoading.value = false;
    }
};

const showDeleteDraft = ref(null);
const draftDeleteProcessing = ref(false);

const runDeleteDraft = () => {
    const id = showDeleteDraft.value;
    if (!id) {
        return;
    }
    draftDeleteProcessing.value = true;
    router.delete(
        route('organizations.events.team-drafts.destroy', [slug.value, eventId.value, id]),
        {
            preserveScroll: true,
            onFinish: () => {
                draftDeleteProcessing.value = false;
                showDeleteDraft.value = null;
            },
        },
    );
};

const finalizeDraft = (id) => {
    router.post(
        route('organizations.events.team-drafts.finalize', [slug.value, eventId.value, id]),
        {},
        { preserveScroll: true },
    );
};

const showRevert = ref(false);
const revertProcessing = ref(false);
const runRevert = () => {
    revertProcessing.value = true;
    router.post(
        route('organizations.events.revert-final', [slug.value, eventId.value]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                revertProcessing.value = false;
                showRevert.value = false;
            },
        },
    );
};

/* ——— Final grid helpers ——— */
const finalState = computed(() =>
    props.final_draft?.state && typeof props.final_draft.state === 'object' ? props.final_draft.state : null,
);

const memberById = computed(() => {
    const m = new Map();
    (props.orgMembers || []).forEach((mem) => m.set(mem.id, mem.display_name));

    return m;
});

const displayMember = (id) => memberById.value.get(id) ?? `#${id}`;

const memberFullById = computed(() => {
    const m = new Map();
    (props.orgMembers || []).forEach((mem) => m.set(mem.id, mem));
    return m;
});

const memberScreenDetails = (mid) =>
    buildMemberDetails(mid, memberFullById.value.get(mid), selectedExportColumns.value, displayMember(mid));

// Arrange members in a roughly square grid — ceil(√n), max 4 columns
const memberCardColumns = (count) => Math.min(4, Math.ceil(Math.sqrt(count || 1)));

const showGroupLabel = computed(() =>
    selectedExportColumns.value.includes('group_index') ||
    selectedExportColumns.value.includes('group_name'),
);

const showTeamLabel = computed(() =>
    selectedExportColumns.value.includes('team_index') ||
    selectedExportColumns.value.includes('team_name'),
);

// Build a label showing only the parts whose columns are selected, in column order
const groupLabelForDisplay = (gi) => {
    const cols = selectedExportColumns.value;
    const letter = indexToLetter(gi);
    const name = String(finalGroupNames.value?.[gi] ?? '').trim();
    const parts = cols.reduce((acc, col) => {
        if (col === 'group_index') acc.push(letter);
        else if (col === 'group_name' && name) acc.push(name);
        return acc;
    }, []);
    if (parts.length) return parts.join(' · ');
    // Fallback when only group_name is selected but name is empty
    return cols.includes('group_name') ? (name || letter) : letter;
};

const teamLabelForDisplay = (ti) => {
    const cols = selectedExportColumns.value;
    const num = String(ti + 1);
    const name = String(finalTeamNames.value?.[ti] ?? '').trim();
    const parts = cols.reduce((acc, col) => {
        if (col === 'team_index') acc.push(num);
        else if (col === 'team_name' && name && name !== num) acc.push(name);
        return acc;
    }, []);
    if (parts.length) return parts.join(' · ');
    // Fallback when only team_name is selected but name is same as num or empty
    if (cols.includes('team_name')) return name || num;
    return '';
};

const eventNameById = computed(() => {
    const m = new Map();
    (props.finalizedEvents || []).forEach((e) => m.set(e.id, e.name));
    return m;
});

const memberAttributeMatchOptions = computed(() => {
    const attr = ruleForm.config?.attribute;
    if (attr === 'gender') {
        return [
            { value: 'same', label: 'Avoid same gender (e.g. encourage mixed teams)' },
            { value: 'different', label: 'Avoid different genders (e.g. keep single-gender teams)' },
        ];
    }
    if (attr === 'company') {
        return [
            { value: 'same', label: 'Avoid same company (e.g. mix companies on each team)' },
            { value: 'different', label: 'Avoid different companies (e.g. keep same company together)' },
        ];
    }
    // default: sector_id
    return [
        { value: 'same', label: 'Avoid same sector (e.g. don\'t group same sector)' },
        { value: 'different', label: 'Avoid different sectors (e.g. keep same sector together)' },
    ];
});

const ruleConfigSummary = (rule) => {
    const cfg = rule.config ?? {};
    switch (rule.type) {
        case 'size':
            return rule.scope === 'group'
                ? `${cfg.size ?? '?'} teams per group`
                : `${cfg.size ?? '?'} members per team`;
        case 'banned_pair':
        case 'preferred_pair': {
            const a = displayMember(cfg.member_a_id);
            const b = displayMember(cfg.member_b_id);
            return `${a} & ${b}`;
        }
        case 'repeat_pair': {
            const name = eventNameById.value.get(cfg.event_id) ?? `Event #${cfg.event_id}`;
            return `Avoid repeating pairs from: ${name}`;
        }
        case 'skill_leveling':
            return `Avg skill ${cfg.min_avg ?? '?'} – ${cfg.max_avg ?? '?'}`;
        case 'member_attribute': {
            const attrLabels = { sector_id: 'sector', company: 'company', gender: 'gender' };
            const attrLabel = attrLabels[cfg.attribute] ?? cfg.attribute ?? 'attribute';
            const matchLabel = cfg.match === 'same' ? 'Avoid same' : 'Avoid different';
            return `${matchLabel} ${attrLabel}`;
        }
        default:
            return null;
    }
};

const finalTeams = computed(() => (Array.isArray(finalState.value?.teams) ? finalState.value.teams : []));
const finalTeamNames = computed(() => finalState.value?.team_names || []);
const finalGroups = computed(() => (Array.isArray(finalState.value?.groups) ? finalState.value.groups : []));
const finalGroupNames = computed(() => finalState.value?.group_names || []);

const nonFinalDrafts = computed(() => props.team_drafts || []);
const finalDraftSummary = computed(() => props.final_draft ?? null);
const finalViolationCount = computed(() =>
    Array.isArray(finalState.value?.violations) ? finalState.value.violations.length : 0,
);
const finalBlockingErrors = computed(() =>
    Array.isArray(finalState.value?.blocking_errors) ? finalState.value.blocking_errors : [],
);

const columnsOpen = ref(false);
const showViolations = ref(true);

const showSkillColumn = computed(() => selectedExportColumns.value.includes('skill'));

const finalTeamAvgSkill = computed(() => {
    if (!showSkillColumn.value) return new Map();
    const m = new Map();
    finalTeams.value.forEach((team, ti) => {
        m.set(ti, avgSkill(team.member_ids || [], memberFullById.value));
    });
    return m;
});

const finalGroupAvgSkill = computed(() => {
    if (!showSkillColumn.value) return new Map();
    const m = new Map();
    finalGroups.value.forEach((group, gi) => {
        const memberIds = (group.team_indices || []).flatMap(
            (ti) => finalTeams.value[ti]?.member_ids || [],
        );
        m.set(gi, avgSkill(memberIds, memberFullById.value));
    });
    return m;
});

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
</script>

<template>
    <Head :title="event.name" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div>
                <h1 class="ts-heading-page">{{ event.name }}</h1>
                <p class="text-sm text-brand-blue/70">
                    {{ event.event_type?.name }} · {{ event.event_date }}
                    <Badge v-if="event.is_finalized" class="ml-2" variant="success">Finalized</Badge>
                </p>
            </div>
        </template>

        <nav class="mb-8 flex flex-wrap gap-2 border-b border-brand-mist pb-2">
            <Link
                v-for="t in ['details', 'roster', 'rules', 'drafts']"
                :key="t"
                :href="eventHref(t)"
                class="rounded-lg px-3 py-2 text-sm font-medium capitalize"
                :class="
                    tab === t
                        ? 'bg-brand-navy text-white'
                        : 'bg-brand-mist/80 text-brand-navy hover:bg-brand-mist'
                "
            >
                {{ t === 'drafts' ? 'Teams' : t.charAt(0).toUpperCase() + t.slice(1) }}
            </Link>
        </nav>

        <!-- Details -->
        <section v-if="tab === 'details'" class="space-y-8">
            <form v-if="canManage" class="ts-card-padded space-y-5" @submit.prevent="saveDetails">
                <h2 class="ts-heading-section">Event details</h2>
                <Alert v-if="detailsFormHasErrors" variant="error" role="alert">
                    Please fix the errors below before saving.
                </Alert>
                <FormField label="Name" name="ev_name" :error="detailsForm.errors.name" hint="Required." required>
                    <TextInput id="ev_name" v-model="detailsForm.name" :error="!!detailsForm.errors.name" />
                </FormField>
                <FormField label="Description" name="ev_desc" :error="detailsForm.errors.description">
                    <textarea
                        id="ev_desc"
                        v-model="detailsForm.description"
                        rows="4"
                        class="ts-input w-full resize-y rounded-lg py-2 text-sm"
                        :class="detailsForm.errors.description ? 'ts-input-error' : ''"
                    />
                </FormField>
                <FormField label="Date" name="ev_date" :error="detailsForm.errors.event_date" hint="Required." required>
                    <DateInput id="ev_date" v-model="detailsForm.event_date" :error="!!detailsForm.errors.event_date" />
                </FormField>
                <FormField
                    label="Event type"
                    name="ev_type"
                    :error="detailsForm.errors.event_type_id"
                    hint="Required."
                    required
                >
                    <ListboxInput
                        v-model="detailsForm.event_type_id"
                        :options="eventTypeOptions"
                        placeholder="Type…"
                        :error="!!detailsForm.errors.event_type_id"
                    />
                </FormField>
                <div>
                    <Toggle v-model="detailsForm.uses_groups" label="Uses groups" />
                    <p v-if="detailsForm.errors.uses_groups" class="mt-1 text-sm text-red-600">
                        {{ detailsForm.errors.uses_groups }}
                    </p>
                </div>
                <div>
                    <span class="block text-sm font-medium text-brand-navy">Previous events</span>
                    <p class="mb-2 text-xs text-brand-blue/60">Link finalized events for "avoid same members" rules.</p>
                    <ComboboxInput
                        v-model="detailsForm.previous_event_ids"
                        :options="priorEventCombobox"
                        label-key="label"
                        value-key="id"
                        multiple
                        placeholder="Prior events…"
                        :error="!!detailsForm.errors.previous_event_ids"
                    />
                </div>
                <PrimaryButton type="submit" :disabled="detailsForm.processing">
                    {{ detailsForm.processing ? 'Saving…' : 'Save details' }}
                </PrimaryButton>
            </form>
            <Alert v-else variant="info">You can view this event but not edit it.</Alert>

            <div v-if="canManage" class="flex flex-wrap gap-3">
                <SecondaryButton type="button" @click="showDup = true">Duplicate event</SecondaryButton>
                <DangerButton type="button" @click="showDeleteEvent = true">Delete event</DangerButton>
            </div>
        </section>

        <!-- Roster -->
        <section v-else-if="tab === 'roster' && roster != null" class="space-y-8">
            <div class="flex flex-wrap gap-2">
                <Badge variant="neutral">{{ rsvp.included ?? 0 }} included</Badge>
                <Badge variant="info">{{ rsvp.invited ?? 0 }} invited</Badge>
                <Badge variant="success">{{ rsvp.accepted ?? 0 }} accepted</Badge>
                <Badge variant="warning">{{ rsvp.pending ?? 0 }} pending</Badge>
                <Badge variant="danger">{{ rsvp.declined ?? 0 }} declined</Badge>
                <Badge variant="neutral">{{ rsvp.waiting ?? 0 }} waiting list</Badge>
            </div>

            <Alert
                v-if="rosterNotice"
                :variant="rosterNotice.variant"
                role="alert"
            >
                {{ rosterNotice.message }}
            </Alert>

            <!-- Inline panels when roster is empty; buttons → modals once members exist -->
            <template v-if="canManage">
                <div v-if="rosterRows.length === 0" class="grid gap-6 lg:grid-cols-2">
                    <div class="ts-card-padded lg:col-span-2 xl:col-span-1">
                        <h3 class="mb-1 text-sm font-semibold text-brand-navy">Add members from organization</h3>
                        <p class="mb-4 text-xs text-brand-blue/60">
                            Select one or more members, then add them to the roster in one step.
                        </p>
                        <RosterMemberPicker
                            v-model="selectedMemberIdsToAdd"
                            :members="availableMembersToAdd"
                        />
                        <PrimaryButton
                            class="mt-4"
                            type="button"
                            :disabled="addMembersProcessing || selectedMemberIdsToAdd.length === 0"
                            @click="postAddMembers"
                        >
                            {{
                                addMembersProcessing
                                    ? 'Adding…'
                                    : selectedMemberIdsToAdd.length === 1
                                      ? 'Add 1 member to roster'
                                      : `Add ${selectedMemberIdsToAdd.length} members to roster`
                            }}
                        </PrimaryButton>
                    </div>
                    <div class="ts-card-padded">
                        <h3 class="mb-2 text-sm font-semibold text-brand-navy">Copy from event</h3>
                        <p class="mb-3 text-xs text-brand-blue/60">
                            Copy the full roster from another event in this organization.
                        </p>
                        <ComboboxInput v-model="copyFromId" :options="copyEventOptions" placeholder="Pick event…" />
                        <SecondaryButton class="mt-3" type="button" @click="postCopy">
                            Copy roster
                        </SecondaryButton>
                    </div>
                </div>
                <div v-else class="flex flex-wrap gap-3">
                    <SecondaryButton type="button" @click="openAddMembersModal">
                        Add members
                    </SecondaryButton>
                    <SecondaryButton type="button" @click="openCopyModal">
                        Copy from event
                    </SecondaryButton>
                </div>
            </template>

            <div v-if="canManage && selectedEmIds.size" class="ts-card-padded flex flex-wrap items-center gap-2">
                <span class="text-sm text-brand-navy">{{ selectedEmIds.size }} selected</span>
                <SecondaryButton type="button" @click="bulkPatch({ included: true })">Include</SecondaryButton>
                <SecondaryButton type="button" @click="bulkPatch({ included: false })">Move to waiting list</SecondaryButton>
                <SecondaryButton type="button" @click="bulkPatch({ invited: true })">Set invited</SecondaryButton>
                <SecondaryButton type="button" @click="bulkPatch({ invited: false })">Set not invited</SecondaryButton>
                <SecondaryButton type="button" @click="bulkPatch({ status: 'accepted' })">Set accepted</SecondaryButton>
                <SecondaryButton type="button" @click="bulkPatch({ status: 'declined' })">Set declined</SecondaryButton>
            </div>

            <div class="overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
                <table class="min-w-full divide-y divide-brand-mist text-sm">
                    <thead class="bg-brand-cream">
                        <tr>
                            <th v-if="canManage" class="w-10 px-2 py-2">
                                <input
                                    type="checkbox"
                                    :checked="allIncludedSelected"
                                    :indeterminate="someIncludedSelected"
                                    class="rounded border-brand-mist"
                                    :aria-label="allIncludedSelected ? 'Deselect all' : 'Select all'"
                                    @change="toggleSelectAll(rosterIncluded, $event.target.checked)"
                                />
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                    @click="setRosterSort('last_name')"
                                >
                                    Member
                                    <span class="text-[10px]" aria-hidden="true">
                                        <template v-if="rosterSort.column === 'last_name'">
                                            {{ rosterSort.direction === 'asc' ? '▲' : '▼' }}
                                        </template>
                                        <template v-else>⇅</template>
                                    </span>
                                </button>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                    @click="setRosterSort('included')"
                                >
                                    Included
                                    <span class="text-[10px]" aria-hidden="true">
                                        <template v-if="rosterSort.column === 'included'">
                                            {{ rosterSort.direction === 'asc' ? '▲' : '▼' }}
                                        </template>
                                        <template v-else>⇅</template>
                                    </span>
                                </button>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                    @click="setRosterSort('invited')"
                                >
                                    Invited
                                    <span class="text-[10px]" aria-hidden="true">
                                        <template v-if="rosterSort.column === 'invited'">
                                            {{ rosterSort.direction === 'asc' ? '▲' : '▼' }}
                                        </template>
                                        <template v-else>⇅</template>
                                    </span>
                                </button>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                    @click="setRosterSort('status')"
                                >
                                    Status
                                    <span class="text-[10px]" aria-hidden="true">
                                        <template v-if="rosterSort.column === 'status'">
                                            {{ rosterSort.direction === 'asc' ? '▲' : '▼' }}
                                        </template>
                                        <template v-else>⇅</template>
                                    </span>
                                </button>
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-mist">
                        <tr v-for="row in rosterIncluded" :key="row.id" class="align-top">
                            <td v-if="canManage" class="px-2 py-2">
                                <input
                                    type="checkbox"
                                    :checked="selectedEmIds.has(row.id)"
                                    class="rounded border-brand-mist"
                                    @change="toggleSelect(row.id, $event.target.checked)"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-brand-navy">{{ row.display_name }}</div>
                                <div class="text-xs text-brand-blue/60">{{ row.email || '—' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <Toggle
                                    v-model="row.included"
                                    :disabled="!canManage"
                                    @update:model-value="(v) => patchMember(row, { included: v })"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <Toggle
                                    v-model="row.invited"
                                    :disabled="!canManage"
                                    @update:model-value="(v) => patchMember(row, { invited: v })"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <StatusBadge
                                    :model-value="row.status"
                                    :included="row.included"
                                    :disabled="!canManage"
                                    @update:model-value="(v) => patchMember(row, { status: v })"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <TextInput
                                    v-if="canManage"
                                    v-model="localNotes[row.id]"
                                    class="min-w-[10rem] text-xs"
                                    @blur="
                                        () => {
                                            const v = localNotes[row.id];
                                            if ((v || '') !== (row.notes || '')) {
                                                patchMember(row, { notes: v || null });
                                            }
                                        }
                                    "
                                />
                                <span v-else class="text-brand-blue/80">{{ row.notes || '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="rosterWaiting.length">
                <h3 class="ts-heading-section mb-3">Waiting list</h3>
                <div class="overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-brand-mist text-sm">
                        <thead class="bg-brand-cream">
                            <tr>
                                <th v-if="canManage" class="w-10 px-2 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="allWaitingSelected"
                                        :indeterminate="someWaitingSelected"
                                        class="rounded border-brand-mist"
                                        :aria-label="allWaitingSelected ? 'Deselect all' : 'Select all'"
                                        @change="toggleSelectAll(rosterWaiting, $event.target.checked)"
                                    />
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">Member</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">Include</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rosterWaiting" :key="row.id" class="align-top">
                                <td v-if="canManage" class="px-2 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="selectedEmIds.has(row.id)"
                                        class="rounded border-brand-mist"
                                        @change="toggleSelect(row.id, $event.target.checked)"
                                    />
                                </td>
                                <td class="px-3 py-2">{{ row.display_name }}</td>
                                <td class="px-3 py-2">
                                    <Toggle
                                        v-model="row.included"
                                        :disabled="!canManage"
                                        @update:model-value="(v) => patchMember(row, { included: v })"
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge
                                        :model-value="row.status"
                                        :included="row.included"
                                        :disabled="true"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Roster modals -->
            <Modal v-if="canManage" :show="showAddMembersModal" max-width="xl" @close="showAddMembersModal = false">
                <div class="p-6">
                    <h3 class="mb-1 text-lg font-semibold text-brand-navy">Add members from organization</h3>
                    <p class="mb-4 text-sm text-brand-blue/60">
                        Select one or more members, then add them to the roster in one step.
                    </p>
                    <RosterMemberPicker
                        v-model="selectedMemberIdsToAdd"
                        :members="availableMembersToAdd"
                    />
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showAddMembersModal = false">Cancel</SecondaryButton>
                        <PrimaryButton
                            type="button"
                            :disabled="addMembersProcessing || selectedMemberIdsToAdd.length === 0"
                            @click="postAddMembers"
                        >
                            {{
                                addMembersProcessing
                                    ? 'Adding…'
                                    : selectedMemberIdsToAdd.length === 1
                                      ? 'Add 1 member to roster'
                                      : `Add ${selectedMemberIdsToAdd.length} members to roster`
                            }}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <Modal v-if="canManage" :show="showCopyModal" max-width="md" @close="showCopyModal = false">
                <div class="p-6">
                    <h3 class="mb-2 text-lg font-semibold text-brand-navy">Copy from event</h3>
                    <p class="mb-4 text-sm text-brand-blue/60">
                        Copy the full roster from another event in this organization.
                    </p>
                    <Alert v-if="copyModalNotice" :variant="copyModalNotice.variant" role="alert" class="mb-4">
                        {{ copyModalNotice.message }}
                    </Alert>
                    <ComboboxInput v-model="copyFromId" :options="copyEventOptions" placeholder="Pick event…" />
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showCopyModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="button" :disabled="!copyFromId" @click="postCopy">
                            Copy roster
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </section>

        <!-- Rules -->
        <section v-else-if="tab === 'rules' && rules" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="ts-heading-section m-0">Rules</h2>
                <PrimaryButton v-if="canManage" type="button" @click="openAddRule">Add rule</PrimaryButton>
            </div>

            <TransitionGroup
                v-if="rules.length"
                name="ts-rule"
                tag="ul"
                class="space-y-3"
            >
                <li
                    v-for="r in rules"
                    :key="r.id"
                    class="rounded-xl border border-brand-mist bg-white p-4 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-brand-navy">
                                {{ ruleTypeLabels[r.type] || r.type }}
                                <span class="text-xs font-normal text-brand-blue/60">
                                    · {{ scopeLabels[r.scope] || r.scope }} · weight {{ r.weight }}
                                </span>
                            </p>
                            <p v-if="ruleConfigSummary(r)" class="mt-1 text-sm text-brand-blue/70">
                                {{ ruleConfigSummary(r) }}
                            </p>
                        </div>
                        <div v-if="canManage" class="flex gap-2">
                            <SecondaryButton type="button" @click="openEditRule(r)">Edit</SecondaryButton>
                            <DangerButton type="button" @click="deleteRule(r.id)">Delete</DangerButton>
                        </div>
                    </div>
                </li>
            </TransitionGroup>
            <EmptyState
                v-else
                title="No rules yet"
                description="Add constraints for the team solver."
                v-bind="canManage ? { onClick: openAddRule } : {}"
            />
        </section>

        <!-- Drafts (includes finalized draft at top) -->
        <section v-else-if="tab === 'drafts' && team_drafts" class="space-y-8">
            <Alert
                v-if="draftsNotice"
                :variant="draftsNotice.variant"
                role="alert"
            >
                {{ draftsNotice.message }}
            </Alert>

            <!-- ── Final draft ── -->
            <div v-if="final_draft && finalState" class="space-y-6 rounded-2xl border-2 border-green-300 bg-green-50 p-6 shadow-sm">
                <!-- Header -->
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800 ring-1 ring-inset ring-green-300">
                                ✓ Final
                            </span>
                            <Link
                                class="font-semibold text-brand-navy hover:underline"
                                :href="route('organizations.events.team-drafts.show', [organization.slug, event.id, final_draft.id])"
                            >
                                {{ finalDraftSummary?.name || `Draft #${final_draft.id}` }}
                            </Link>
                        </div>
                        <p class="text-xs text-brand-blue/60">
                            {{ finalDraftSummary?.creator_name || 'Unknown' }}
                            <template v-if="finalDraftSummary?.created_at">
                                · {{ new Date(finalDraftSummary.created_at).toLocaleString() }}
                            </template>
                        </p>
                    </div>
                    <ProgressRing
                        :value="Math.min(finalViolationCount, 20)"
                        :max="20"
                        :size="44"
                    />
                </div>

                <!-- Penalty / violations summary -->
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span class="font-medium text-brand-blue/80">
                        Penalty: {{ finalState.total_penalty ?? '—' }}
                    </span>
                    <template v-if="showViolations">
                        <span
                            v-if="finalViolationCount > 0"
                            class="font-medium text-amber-700"
                        >
                            {{ finalViolationCount }} violation{{ finalViolationCount === 1 ? '' : 's' }}
                        </span>
                        <span v-else class="text-green-700">No violations</span>
                    </template>
                </div>

                <Alert v-if="showViolations && finalBlockingErrors.length" variant="error" class="text-xs">
                    {{ finalBlockingErrors.join('; ') }}
                </Alert>

                <!-- Column selection — collapsible -->
                <div class="border-t border-green-200 pt-5">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between text-sm font-medium text-brand-navy"
                        @click="columnsOpen = !columnsOpen"
                    >
                        Columns
                        <ChevronDownIcon
                            class="h-4 w-4 text-brand-blue/50 transition-transform duration-200"
                            :class="{ 'rotate-180': columnsOpen }"
                        />
                    </button>

                    <div v-show="columnsOpen" class="mt-3 space-y-3">
                        <!-- Selected columns — drag to reorder -->
                        <div class="flex min-h-[48px] flex-wrap gap-2 rounded-lg border border-green-200 bg-white p-3">
                            <p v-if="!selectedExportColumns.length" class="text-sm italic text-brand-blue/50">
                                No columns selected — nothing will export.
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
                                    class="ml-1 text-brand-blue/50 hover:text-brand-navy"
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
                                    class="inline-flex items-center gap-1 rounded-md border border-brand-mist bg-white px-2 py-1 text-xs font-medium text-brand-blue/70 transition-colors hover:border-brand-blue/30 hover:text-brand-navy"
                                    @click="addExportColumn(col.value)"
                                >
                                    <PlusIcon class="h-3 w-3" />
                                    {{ col.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions: export icon buttons + violations toggle + revert -->
                <div class="flex flex-wrap items-center gap-3 border-t border-green-200 pt-5">
                    <button
                        type="button"
                        title="Print"
                        aria-label="Print"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-green-50"
                        @click="handlePrint"
                    >
                        <PrinterIcon class="h-4 w-4" />
                        Print
                    </button>
                    <a
                        :href="csvExportUrl"
                        title="Download CSV"
                        aria-label="Download CSV"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-green-50"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        CSV
                    </a>
                    <a
                        :href="xlsxExportUrl"
                        title="Download Excel"
                        aria-label="Download Excel"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-white px-3 py-1.5 text-xs font-medium text-brand-navy shadow-sm hover:bg-green-50"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        XLS
                    </a>

                    <Toggle v-model="showViolations" label="Show violations" class="ml-2" />

                    <DangerButton v-if="canRevertFinal" type="button" class="ml-auto" @click="showRevert = true">
                        Revert to draft
                    </DangerButton>
                </div>

                <!-- Grouped view -->
                <div v-if="finalGroups.length" class="space-y-8">
                    <section v-for="(group, gi) in finalGroups" :key="`fg${gi}`">
                        <div v-if="showGroupLabel" class="mb-4 flex flex-wrap items-baseline gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-brand-blue/60">
                                {{ groupLabelForDisplay(gi) }}
                            </h3>
                            <span
                                v-if="showSkillColumn && finalGroupAvgSkill.get(gi) != null"
                                class="text-xs text-brand-blue/50"
                            >Avg skill: {{ finalGroupAvgSkill.get(gi) }}</span>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div
                                v-for="ti in group.team_indices || []"
                                :key="`ft${ti}`"
                                class="rounded-xl border border-green-200 bg-white p-4 shadow-sm"
                            >
                                <div v-if="showTeamLabel" class="mb-3 flex flex-wrap items-baseline gap-2">
                                    <h4 class="font-semibold text-brand-navy">{{ teamLabelForDisplay(ti) }}</h4>
                                    <span
                                        v-if="showSkillColumn && finalTeamAvgSkill.get(ti) != null"
                                        class="text-xs text-brand-blue/50"
                                    >Avg skill: {{ finalTeamAvgSkill.get(ti) }}</span>
                                </div>
                                <ul
                                    class="grid gap-2"
                                    :style="{ gridTemplateColumns: `repeat(${memberCardColumns(finalTeams[ti]?.member_ids?.length ?? 0)}, 1fr)` }"
                                >
                                    <li
                                        v-for="mid in finalTeams[ti]?.member_ids || []"
                                        :key="mid"
                                        class="rounded-lg bg-green-50 px-2.5 py-2"
                                    >
                                        <template v-for="d in memberScreenDetails(mid)" :key="d.key">
                                            <p v-if="d.isName" class="text-sm font-medium leading-snug text-brand-navy">{{ d.value }}</p>
                                            <span v-else class="block text-xs text-brand-blue/60">{{ d.value }}</span>
                                        </template>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Flat view (no groups) -->
                <div v-else class="grid gap-6 lg:grid-cols-2">
                    <section
                        v-for="(t, ti) in finalTeams"
                        :key="`ft${ti}`"
                        class="rounded-xl border border-green-200 bg-white p-4 shadow-sm"
                    >
                        <div v-if="showTeamLabel" class="mb-3 flex flex-wrap items-baseline gap-2">
                            <h4 class="font-semibold text-brand-navy">{{ teamLabelForDisplay(ti) }}</h4>
                            <span
                                v-if="showSkillColumn && finalTeamAvgSkill.get(ti) != null"
                                class="text-xs text-brand-blue/50"
                            >Avg skill: {{ finalTeamAvgSkill.get(ti) }}</span>
                        </div>
                        <ul
                            class="grid gap-2"
                            :style="{ gridTemplateColumns: `repeat(${memberCardColumns(t.member_ids?.length ?? 0)}, 1fr)` }"
                        >
                            <li
                                v-for="mid in t.member_ids || []"
                                :key="mid"
                                class="rounded-lg bg-green-50 px-2.5 py-2"
                            >
                                <template v-for="d in memberScreenDetails(mid)" :key="d.key">
                                    <p v-if="d.isName" class="text-sm font-medium leading-snug text-brand-navy">{{ d.value }}</p>
                                    <span v-else class="block text-xs text-brand-blue/60">{{ d.value }}</span>
                                </template>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- ── Generate form ── -->
            <div class="ts-card-padded">
                <h2 class="ts-heading-section mb-4">Generate draft</h2>
                <div class="mb-4 flex flex-wrap gap-3">
                    <SecondaryButton type="button" :disabled="conflictsLoading" @click="loadConflicts">
                        {{ conflictsLoading ? 'Loading…' : 'Preview planning conflicts' }}
                    </SecondaryButton>
                </div>
                <Alert v-if="conflictsPreview?.length" variant="warning" class="mb-4">
                    <ul class="list-inside list-disc space-y-1 text-xs">
                        <li v-for="(c, i) in conflictsPreview" :key="`cp${i}`">{{ typeof c === 'string' ? c : JSON.stringify(c) }}</li>
                    </ul>
                </Alert>
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitGenerate">
                    <Alert v-if="genFormHasErrors" variant="error" class="md:col-span-2" role="alert">
                        Fix the errors below before generating.
                    </Alert>
                    <FormField label="Draft name (optional)" name="draft_name" :error="genForm.errors.name">
                        <TextInput id="draft_name" v-model="genForm.name" :error="!!genForm.errors.name" />
                    </FormField>
                    <FormField
                        label="Iterations"
                        name="draft_iterations"
                        :error="genForm.errors.iterations"
                        hint="100–20,000 solver passes."
                    >
                        <TextInput
                            id="draft_iterations"
                            v-model.number="genForm.iterations"
                            inputmode="numeric"
                            :error="!!genForm.errors.iterations"
                        />
                    </FormField>
                    <div class="flex items-end md:col-span-2">
                        <Toggle v-model="genForm.include_pending" label="Include pending RSVPs" />
                    </div>
                    <div class="md:col-span-2">
                        <PrimaryButton type="submit" :disabled="genForm.processing">
                            {{ genForm.processing ? 'Generating…' : 'Generate draft' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- ── Non-final draft cards ── -->
            <div v-if="nonFinalDrafts.length" class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="d in nonFinalDrafts"
                    :key="d.id"
                    class="rounded-xl border border-brand-mist bg-white p-4 shadow-sm"
                    :class="
                        d.total_penalty != null && d.total_penalty > 40
                            ? 'border-red-200 bg-red-50/40'
                            : d.total_penalty != null && d.total_penalty > 15
                              ? 'border-amber-200 bg-amber-50/40'
                              : ''
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <Link
                                class="font-semibold text-brand-navy hover:underline"
                                :href="route('organizations.events.team-drafts.show', [organization.slug, event.id, d.id])"
                            >
                                {{ d.name || `Draft #${d.id}` }}
                            </Link>
                            <p class="text-xs text-brand-blue/60">
                                {{ d.creator_name || 'Unknown' }} ·
                                {{ d.created_at ? new Date(d.created_at).toLocaleString() : '' }}
                            </p>
                        </div>
                        <ProgressRing
                            :value="Math.min(d.violation_count ?? 0, 20)"
                            :max="20"
                            :size="44"
                        />
                    </div>
                    <p class="mt-3 text-sm text-brand-blue/80">
                        Penalty: {{ d.total_penalty ?? '—' }} · Violations: {{ d.violation_count ?? 0 }}
                    </p>
                    <Alert v-if="d.blocking_errors?.length" variant="error" class="mt-3 text-xs">
                        {{ d.blocking_errors.join('; ') }}
                    </Alert>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            class="text-sm font-medium text-brand-blue hover:underline"
                            :href="route('organizations.events.team-drafts.show', [organization.slug, event.id, d.id])"
                        >
                            Open
                        </Link>
                        <SecondaryButton
                            v-if="canFinalize && !event.is_finalized"
                            type="button"
                            class="text-sm"
                            @click="requestFinalizeDraft(d)"
                        >
                            Set as final
                        </SecondaryButton>
                        <DangerButton
                            v-if="canManage"
                            type="button"
                            class="text-sm"
                            @click="showDeleteDraft = d.id"
                        >
                            Delete
                        </DangerButton>
                    </div>
                </div>
            </div>
            <p v-else-if="!final_draft" class="text-sm text-brand-blue/60">No drafts yet. Generate one above.</p>
        </section>

        <!-- Fallback -->
        <section v-else>
            <EmptyState title="Nothing to show" description="Pick another tab." />
        </section>

        <!-- Modals -->
        <Modal :show="showDup" @close="showDup = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-brand-navy">Duplicate event</h3>
                <p class="mt-2 text-sm text-brand-blue/70">Optionally override the name; roster and rules are copied.</p>
                <FormField class="mt-4" label="Name" name="dup_name" :error="dupForm.errors.name">
                    <TextInput id="dup_name" v-model="dupForm.name" placeholder="Leave blank for “(Copy)”" />
                </FormField>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDup = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="dupForm.processing" @click="submitDup">Duplicate</PrimaryButton>
                </div>
            </div>
        </Modal>

        <ConfirmDialog
            :show="showDeleteEvent"
            title="Delete this event?"
            message="This removes the event and related data."
            confirm-label="Delete"
            :processing="deleteProcessing"
            @close="showDeleteEvent = false"
            @confirm="runDeleteEvent"
        />

        <Modal :show="ruleModalOpen && canManage" max-width="2xl" @close="ruleModalOpen = false">
            <form class="max-h-[85vh] overflow-y-auto p-6" @submit.prevent="submitRule">
                <h3 class="text-lg font-semibold text-brand-navy">
                    {{ editingRuleId ? 'Edit rule' : 'Add rule' }}
                </h3>
                <Alert v-if="ruleFormHasErrors" variant="error" class="mt-4" role="alert">
                    Please fix the errors below before saving the rule.
                </Alert>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <FormField label="Type" name="rule_type" :error="ruleForm.errors.type" required>
                        <ListboxInput
                            v-model="ruleForm.type"
                            :options="typeOptions"
                            :error="!!ruleForm.errors.type"
                        />
                    </FormField>
                    <FormField label="Scope" name="rule_scope" :error="ruleForm.errors.scope" required>
                        <ListboxInput
                            v-model="ruleForm.scope"
                            :options="scopeOptionsFiltered"
                            :error="!!ruleForm.errors.scope"
                        />
                    </FormField>
                    <div class="md:col-span-2">
                        <span class="block text-sm font-medium text-brand-navy">Weight</span>
                        <WeightSlider v-model="ruleForm.weight" />
                        <p v-if="ruleForm.errors.weight" class="mt-1 text-sm text-red-600">{{ ruleForm.errors.weight }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4 border-t border-brand-mist pt-4">
                    <template v-if="ruleForm.type === 'size'">
                        <FormField
                            :label="ruleForm.scope === 'group' ? 'Teams per group' : 'Members per team'"
                            name="cfg_size"
                            :error="ruleForm.errors['config.size']"
                        >
                            <TextInput
                                id="cfg_size"
                                v-model.number="ruleForm.config.size"
                                inputmode="numeric"
                            />
                        </FormField>
                    </template>
                    <template v-else-if="ruleForm.type === 'banned_pair' || ruleForm.type === 'preferred_pair'">
                        <div>
                            <span class="block text-sm font-medium text-brand-navy">Member A</span>
                            <ComboboxInput v-model="ruleForm.config.member_a_id" :options="memberPairOptions" />
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-brand-navy">Member B</span>
                            <ComboboxInput v-model="ruleForm.config.member_b_id" :options="memberPairOptions" />
                        </div>
                    </template>
                    <template v-else-if="ruleForm.type === 'repeat_pair'">
                        <div>
                            <span class="block text-sm font-medium text-brand-navy">Prior event</span>
                            <ListboxInput
                                v-model="ruleForm.config.event_id"
                                :options="repeatPriorOptions"
                                placeholder="Choose linked prior…"
                            />
                        </div>
                    </template>
                    <template v-else-if="ruleForm.type === 'skill_leveling'">
                        <div>
                            <span class="mb-2 block text-sm text-brand-navy">Min average skill</span>
                            <RangeSlider v-model="ruleForm.config.min_avg" :min="0" :max="100" />
                        </div>
                        <div>
                            <span class="mb-2 block text-sm text-brand-navy">Max average skill</span>
                            <RangeSlider v-model="ruleForm.config.max_avg" :min="0" :max="100" />
                        </div>
                    </template>
                    <template v-else-if="ruleForm.type === 'member_attribute'">
                        <FormField label="Attribute" name="cfg_attribute" :error="ruleForm.errors['config.attribute']">
                            <ListboxInput
                                v-model="ruleForm.config.attribute"
                                :options="[
                                    { value: 'sector_id', label: 'Sector' },
                                    { value: 'company', label: 'Company' },
                                    { value: 'gender', label: 'Gender' },
                                ]"
                            />
                        </FormField>
                        <FormField label="Constraint" name="cfg_match" :error="ruleForm.errors['config.match']">
                            <ListboxInput
                                v-model="ruleForm.config.match"
                                :options="memberAttributeMatchOptions"
                            />
                        </FormField>
                    </template>
                </div>

                <p v-if="ruleForm.errors.config" class="mt-4 text-sm text-red-600">{{ ruleForm.errors.config }}</p>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="ruleModalOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="ruleForm.processing">Save rule</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="showDeleteDraft !== null"
            title="Delete this draft?"
            confirm-label="Delete"
            :processing="draftDeleteProcessing"
            @close="showDeleteDraft = null"
            @confirm="runDeleteDraft"
        />

        <ConfirmDialog
            :show="showRevert"
            title="Revert finalized teams?"
            message="Admin only: clears finalization so you can draft again."
            confirm-label="Revert"
            :processing="revertProcessing"
            @close="showRevert = false"
            @confirm="runRevert"
        />

        <ConfirmDialog
            :show="draftToFinalize !== null"
            title="Finalize with this draft?"
            message="This locks the final team assignment for the event. You can revert later (admin)."
            confirm-label="Finalize"
            @close="draftToFinalize = null"
            @confirm="confirmFinalizeDraft"
        />
    </OrganizationLayout>
</template>

<style scoped>
.ts-rule-move,
.ts-rule-enter-active,
.ts-rule-leave-active {
    transition: all 0.2s ease;
}
.ts-rule-enter-from,
.ts-rule-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
