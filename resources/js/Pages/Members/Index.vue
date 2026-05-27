<script setup>
import ComboboxInput from '@/Components/ComboboxInput.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ListboxInput from '@/Components/ListboxInput.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SkillSlider from '@/Components/SkillSlider.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import {
    DEFAULT_MEMBER_FILTERS,
    FILTER_ANY,
    FILTER_UNSPECIFIED,
    loadMemberFilterPreference,
    memberMatchesFilters,
    saveMemberFilterPreference,
} from '@/utils/memberFilters';
import { loadMemberSortPreference, saveMemberSortPreference } from '@/utils/memberSort';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    organization: Object,
    members: Array,
    eventTypes: Array,
    sectors: Array,
    canManage: Boolean,
});

const sectorOptions = computed(() =>
    (props.sectors || []).map((s) => ({
        value: s.id,
        label: s.name,
    })),
);

const genderOptions = [
    { value: null, label: '— Not specified —' },
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'non_binary', label: 'Non-binary' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
];

const filterGenderOptions = [
    { value: FILTER_ANY, label: 'Any gender' },
    { value: FILTER_UNSPECIFIED, label: 'Not specified' },
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'non_binary', label: 'Non-binary' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
];

const sectorFilterOptions = computed(() => [
    { value: FILTER_ANY, label: 'Any sector' },
    { value: FILTER_UNSPECIFIED, label: 'Not specified' },
    ...sectorOptions.value,
]);

// ── Add modal ──────────────────────────────────────────────────────────────
const showAdd = ref(false);

const openAdd = () => {
    addForm.reset();
    addForm.skills = defaultSkillsPayload();
    addForm.clearErrors();
    showAdd.value = true;
};

const closeAdd = () => {
    showAdd.value = false;
    addForm.clearErrors();
};

// ── Import modal ───────────────────────────────────────────────────────────
const showImport = ref(false);

const openImport = () => {
    importForm.reset('file');
    importForm.clearErrors();
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    showImport.value = true;
};

const closeImport = () => {
    showImport.value = false;
    importForm.clearErrors();
};

// ── Quick sector form (inside Add modal) ───────────────────────────────────
const quickSectorForm = useForm({
    name: '',
});

const submitQuickSector = () => {
    quickSectorForm.post(route('organizations.sectors.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => quickSectorForm.reset('name'),
    });
};

// ── Import form ────────────────────────────────────────────────────────────
const importForm = useForm({
    file: null,
});

const fileInputRef = ref(null);

const pickFile = (e) => {
    importForm.file = e.target.files?.[0] ?? null;
    importForm.clearErrors('file');
};

const submitImport = () => {
    if (!importForm.file || importForm.processing) {
        return;
    }

    importForm.post(route('organizations.members.import', props.organization.slug), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset('file');
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            closeImport();
        },
    });
};

// ── Add form ───────────────────────────────────────────────────────────────
const defaultSkillsPayload = () =>
    (props.eventTypes || []).map((t) => ({
        event_type_id: t.id,
        skill_level: 50,
    }));

const addForm = useForm({
    first_name: '',
    last_name: '',
    gender: null,
    email: '',
    phone: '',
    company: '',
    sector_id: null,
    notes: '',
    skills: defaultSkillsPayload(),
});

watch(
    () => props.eventTypes,
    () => {
        if (!addForm.skills?.length) {
            addForm.skills = defaultSkillsPayload();
        }
    },
    { deep: true },
);

const skillLevelFor = (skills, eventTypeId) => {
    const row = skills.find((s) => s.event_type_id === eventTypeId);

    return row?.skill_level ?? 50;
};

const setSkillLevel = (skillsRef, eventTypeId, value) => {
    const i = skillsRef.findIndex((s) => s.event_type_id === eventTypeId);
    const next = [...skillsRef];
    if (i === -1) {
        next.push({ event_type_id: eventTypeId, skill_level: value });
    } else {
        next[i] = { ...next[i], skill_level: value };
    }

    return next;
};

const submitAdd = () => {
    addForm.post(route('organizations.members.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addForm.skills = defaultSkillsPayload();
            closeAdd();
        },
    });
};

// ── Edit modal ─────────────────────────────────────────────────────────────
const editingId = ref(null);
const showEdit = ref(false);

const editForm = useForm({
    first_name: '',
    last_name: '',
    gender: null,
    email: '',
    phone: '',
    company: '',
    sector_id: null,
    notes: '',
    skills: [],
});

const openEdit = (m) => {
    editingId.value = m.id;
    editForm.first_name = m.first_name || '';
    editForm.last_name = m.last_name || '';
    editForm.gender = m.gender ?? null;
    editForm.email = m.email || '';
    editForm.phone = m.phone || '';
    editForm.company = m.company || '';
    editForm.sector_id = m.sector_id ?? null;
    editForm.notes = m.notes || '';
    const existing = (m.skills || []).slice();
    const skills = (props.eventTypes || []).map((t) => {
        const hit = existing.find((s) => s.event_type_id === t.id);

        return {
            event_type_id: t.id,
            skill_level: hit?.skill_level ?? 50,
        };
    });
    editForm.skills = skills;
    editForm.clearErrors();
    showEdit.value = true;
};

const closeEdit = () => {
    showEdit.value = false;
    editingId.value = null;
    editForm.clearErrors();
};

const saveEdit = () => {
    if (!editingId.value) {
        return;
    }
    editForm.patch(
        route('organizations.members.update', [props.organization.slug, editingId.value]),
        {
            preserveScroll: true,
            onSuccess: () => closeEdit(),
        },
    );
};

// ── Remove confirm ─────────────────────────────────────────────────────────
const destroyId = ref(null);
const destroyProcessing = ref(false);

const askRemove = (id) => {
    destroyId.value = id;
};

const closeRemove = () => {
    destroyId.value = null;
};

const runRemove = () => {
    if (!destroyId.value) {
        return;
    }
    destroyProcessing.value = true;
    router.delete(
        route('organizations.members.destroy', [props.organization.slug, destroyId.value]),
        {
            preserveScroll: true,
            onFinish: () => {
                destroyProcessing.value = false;
                closeRemove();
            },
        },
    );
};

const restore = (id) => {
    router.post(route('organizations.members.restore', [props.organization.slug, id]), {}, { preserveScroll: true });
};

// ── Filters ────────────────────────────────────────────────────────────────
const initialFilters = loadMemberFilterPreference(props.organization.slug);
const filtersPanelOpen = ref(initialFilters.panelOpen);
const filterSearch = ref(initialFilters.search);
const filterSectorId = ref(initialFilters.sectorId);
const filterGender = ref(initialFilters.gender);
const filterShowRemoved = ref(initialFilters.showRemoved);

const persistFilters = () => {
    saveMemberFilterPreference(props.organization.slug, {
        search: filterSearch.value,
        sectorId: filterSectorId.value,
        gender: filterGender.value,
        showRemoved: filterShowRemoved.value,
        panelOpen: filtersPanelOpen.value,
    });
};

watch(
    [filterSearch, filterSectorId, filterGender, filterShowRemoved, filtersPanelOpen],
    persistFilters,
);

const activeFilterCount = computed(() => {
    let count = 0;
    if (filterSearch.value.trim()) {
        count += 1;
    }
    if (filterSectorId.value !== FILTER_ANY) {
        count += 1;
    }
    if (filterGender.value !== FILTER_ANY) {
        count += 1;
    }
    if (filterShowRemoved.value) {
        count += 1;
    }

    return count;
});

const clearFilters = () => {
    filterSearch.value = DEFAULT_MEMBER_FILTERS.search;
    filterSectorId.value = DEFAULT_MEMBER_FILTERS.sectorId;
    filterGender.value = DEFAULT_MEMBER_FILTERS.gender;
    filterShowRemoved.value = DEFAULT_MEMBER_FILTERS.showRemoved;
};

const filteredMembers = computed(() =>
    (props.members || []).filter((m) =>
        memberMatchesFilters(m, {
            search: filterSearch.value,
            sectorId: filterSectorId.value,
            gender: filterGender.value,
            showRemoved: filterShowRemoved.value,
        }),
    ),
);

// ── Table sorting ──────────────────────────────────────────────────────────
const { key: initialKey, dir: initialDir } = loadMemberSortPreference(props.organization.slug);
const sortKey = ref(initialKey);
const sortDir = ref(initialDir);

const toggleSort = (key) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
    saveMemberSortPreference(props.organization.slug, { key: sortKey.value, dir: sortDir.value });
};

const getSortValue = (m, key) => {
    switch (key) {
        case 'name':
            return `${m.last_name ?? ''} ${m.first_name ?? ''}`.trim().toLowerCase();
        case 'first_name':
            return `${m.first_name ?? ''} ${m.last_name ?? ''}`.trim().toLowerCase();
        case 'company':
            return (m.company ?? '').toLowerCase();
        case 'sector':
            return (m.sector?.name ?? '').toLowerCase();
        default:
            return '';
    }
};

const sortedMembers = computed(() => {
    const list = [...filteredMembers.value];
    const dir = sortDir.value === 'asc' ? 1 : -1;

    return list.sort((a, b) => {
        const va = getSortValue(a, sortKey.value);
        const vb = getSortValue(b, sortKey.value);
        if (va < vb) return -1 * dir;
        if (va > vb) return 1 * dir;
        return 0;
    });
});
</script>

<template>
    <Head title="Members" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="ts-heading-page">Members</h1>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('organizations.sectors.index', organization.slug)"
                        class="text-sm font-medium text-brand-blue hover:text-brand-navy"
                    >
                        Manage sectors
                    </Link>
                    <template v-if="canManage">
                        <SecondaryButton type="button" @click="openImport">
                            Import members
                        </SecondaryButton>
                        <PrimaryButton type="button" @click="openAdd">
                            Add member
                        </PrimaryButton>
                    </template>
                </div>
            </div>
        </template>

        <!-- Members table (hero) -->
        <EmptyState
            v-if="!members?.length"
            title="No members"
            description="Add people to your organization or import a CSV."
            @click="canManage ? openAdd() : undefined"
        >
            <span v-if="canManage" class="text-sm font-medium text-brand-blue/60">
                Click "Add member" to get started
            </span>
        </EmptyState>

        <div v-else class="space-y-4">
            <div class="overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left text-sm font-semibold text-brand-navy transition-colors hover:bg-brand-cream/60 active:bg-brand-cream"
                    :aria-expanded="filtersPanelOpen"
                    @click="filtersPanelOpen = !filtersPanelOpen"
                >
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <span>Filters</span>
                        <span
                            v-if="activeFilterCount"
                            class="rounded-full bg-brand-blue/10 px-2 py-0.5 text-xs font-medium text-brand-blue"
                        >
                            {{ activeFilterCount }} active
                        </span>
                        <span class="text-xs font-normal text-brand-blue/60">
                            {{ filteredMembers.length }} of {{ members.length }} shown
                        </span>
                    </span>
                    <ChevronDownIcon
                        class="h-4 w-4 shrink-0 text-brand-blue/50 transition-transform duration-200"
                        :class="{ 'rotate-180': filtersPanelOpen }"
                    />
                </button>

                <div
                    v-show="filtersPanelOpen"
                    class="space-y-4 border-t border-brand-mist bg-brand-cream/30 p-4"
                >
                    <FormField label="Search" name="member_filter_search">
                        <TextInput
                            id="member_filter_search"
                            v-model="filterSearch"
                            type="search"
                            placeholder="Name, email, phone, company, sector…"
                        />
                    </FormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Sector" name="member_filter_sector">
                            <ListboxInput
                                v-model="filterSectorId"
                                :options="sectorFilterOptions"
                                placeholder="Any sector"
                                portal
                            />
                        </FormField>
                        <FormField label="Gender" name="member_filter_gender">
                            <ListboxInput
                                v-model="filterGender"
                                :options="filterGenderOptions"
                                placeholder="Any gender"
                                portal
                            />
                        </FormField>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-brand-navy">
                        <input
                            v-model="filterShowRemoved"
                            type="checkbox"
                            class="rounded border-brand-mist text-brand-blue focus:ring-brand-blue/30"
                        />
                        Show removed members
                    </label>

                    <div v-if="activeFilterCount" class="flex justify-end">
                        <button
                            type="button"
                            class="cursor-pointer text-sm font-medium text-brand-blue hover:text-brand-navy hover:underline active:opacity-70"
                            @click="clearFilters"
                        >
                            Clear filters
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="!filteredMembers.length"
                class="rounded-xl border border-dashed border-brand-mist bg-brand-cream/50 px-6 py-10 text-center"
            >
                <p class="text-sm font-medium text-brand-navy">No members match your filters</p>
                <p class="mt-1 text-sm text-brand-blue/70">
                    Try adjusting search or filters, or show removed members.
                </p>
                <button
                    v-if="activeFilterCount"
                    type="button"
                    class="mt-4 cursor-pointer text-sm font-medium text-brand-blue hover:text-brand-navy hover:underline active:opacity-70"
                    @click="clearFilters"
                >
                    Clear filters
                </button>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-mist">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                @click="toggleSort('first_name')"
                            >
                                First Name
                                <span class="text-[10px]" aria-hidden="true">
                                    <template v-if="sortKey === 'first_name'">
                                        {{ sortDir === 'asc' ? '▲' : '▼' }}
                                    </template>
                                    <template v-else>⇅</template>
                                </span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                @click="toggleSort('name')"
                            >
                                Last Name
                                <span class="text-[10px]" aria-hidden="true">
                                    <template v-if="sortKey === 'name'">
                                        {{ sortDir === 'asc' ? '▲' : '▼' }}
                                    </template>
                                    <template v-else>⇅</template>
                                </span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            Contact
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                @click="toggleSort('company')"
                            >
                                Org / Company
                                <span class="text-[10px]" aria-hidden="true">
                                    <template v-if="sortKey === 'company'">
                                        {{ sortDir === 'asc' ? '▲' : '▼' }}
                                    </template>
                                    <template v-else>⇅</template>
                                </span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70 md:table-cell">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase hover:text-brand-navy"
                                @click="toggleSort('sector')"
                            >
                                Sector
                                <span class="text-[10px]" aria-hidden="true">
                                    <template v-if="sortKey === 'sector'">
                                        {{ sortDir === 'asc' ? '▲' : '▼' }}
                                    </template>
                                    <template v-else>⇅</template>
                                </span>
                            </button>
                        </th>
                        <th
                            v-if="canManage"
                            class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-brand-blue/70"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr
                        v-for="m in sortedMembers"
                        :key="m.id"
                        :class="m.deleted_at ? 'bg-brand-cream/80 opacity-80' : ''"
                    >
                        <td class="px-4 py-3 text-sm text-brand-navy">
                            {{ m.first_name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-navy">
                            {{ m.last_name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-blue/80">
                            <div>{{ m.email || '—' }}</div>
                            <div class="text-xs text-brand-blue/60">{{ m.phone || '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-blue/80">
                            {{ m.company || '—' }}
                        </td>
                        <td class="hidden px-4 py-3 text-sm text-brand-blue/80 md:table-cell">
                            {{ m.sector?.name || '—' }}
                        </td>
                        <td v-if="canManage" class="px-4 py-3 text-right text-sm">
                            <template v-if="!m.deleted_at">
                                <button
                                    type="button"
                                    class="text-brand-blue hover:underline"
                                    @click="openEdit(m)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="ms-3 text-red-600 hover:underline"
                                    @click="askRemove(m.id)"
                                >
                                    Remove
                                </button>
                            </template>
                            <button
                                v-else
                                type="button"
                                class="text-emerald-700 hover:underline"
                                @click="restore(m.id)"
                            >
                                Restore
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Add member modal -->
        <Modal :show="showAdd && canManage" max-width="xl" @close="closeAdd">
            <form class="max-h-[80vh] overflow-y-auto p-6" @submit.prevent="submitAdd">
                <h2 class="text-lg font-semibold text-brand-navy">Add member</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField label="First name" name="add_fn" :error="addForm.errors.first_name" required>
                        <TextInput id="add_fn" v-model="addForm.first_name" :error="!!addForm.errors.first_name" />
                    </FormField>
                    <FormField label="Last name" name="add_ln" :error="addForm.errors.last_name">
                        <TextInput id="add_ln" v-model="addForm.last_name" :error="!!addForm.errors.last_name" />
                    </FormField>
                </div>
                <FormField label="Gender" name="add_gender" :error="addForm.errors.gender" class="mt-4">
                    <ListboxInput v-model="addForm.gender" :options="genderOptions" />
                </FormField>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField label="Email" name="add_email" :error="addForm.errors.email">
                        <TextInput id="add_email" v-model="addForm.email" :error="!!addForm.errors.email" />
                    </FormField>
                    <FormField label="Phone" name="add_phone" :error="addForm.errors.phone">
                        <TextInput id="add_phone" v-model="addForm.phone" :error="!!addForm.errors.phone" />
                    </FormField>
                </div>
                <FormField label="Organization / Company" name="add_co" :error="addForm.errors.company" class="mt-4">
                    <TextInput id="add_co" v-model="addForm.company" :error="!!addForm.errors.company" />
                </FormField>
                <div class="mt-4">
                    <span class="block text-sm font-medium text-brand-navy">Sector</span>
                    <ComboboxInput
                        v-model="addForm.sector_id"
                        :options="sectorOptions"
                        placeholder="Search sector…"
                        :error="!!addForm.errors.sector_id"
                    />
                    <p v-if="addForm.errors.sector_id" class="mt-1 text-sm text-red-600">
                        {{ addForm.errors.sector_id }}
                    </p>
                </div>

                <div class="mt-4 rounded-lg border border-brand-mist bg-brand-cream/40 p-4">
                    <p class="text-sm font-medium text-brand-navy">Quick add sector</p>
                    <p class="mb-3 text-xs text-brand-blue/65">
                        Name must be unique for this org (or add via the sectors page).
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <TextInput
                            v-model="quickSectorForm.name"
                            class="min-w-48 flex-1"
                            :error="!!quickSectorForm.errors.name"
                            placeholder="New sector…"
                            @keyup.enter.prevent="submitQuickSector"
                        />
                        <SecondaryButton
                            type="button"
                            :disabled="quickSectorForm.processing || !quickSectorForm.name?.trim()"
                            @click="submitQuickSector"
                        >
                            Add sector
                        </SecondaryButton>
                    </div>
                    <p v-if="quickSectorForm.errors.name" class="mt-2 text-sm text-red-600">
                        {{ quickSectorForm.errors.name }}
                    </p>
                </div>

                <FormField label="Notes" name="add_notes" :error="addForm.errors.notes" class="mt-4">
                    <textarea
                        id="add_notes"
                        v-model="addForm.notes"
                        rows="2"
                        class="ts-input w-full resize-y rounded-lg py-2 text-sm"
                        :class="addForm.errors.notes ? 'ts-input-error' : ''"
                    />
                </FormField>

                <div v-if="eventTypes?.length" class="mt-6 space-y-4 border-t border-brand-mist pt-4">
                    <p class="text-sm font-medium text-brand-navy">Skills by event type</p>
                    <div
                        v-for="t in eventTypes"
                        :key="t.id"
                        class="rounded-lg border border-brand-mist/80 bg-white px-3 py-3"
                    >
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-brand-navy">{{ t.name }}</span>
                            <span class="text-xs text-brand-blue/60">
                                {{ skillLevelFor(addForm.skills, t.id) }}
                            </span>
                        </div>
                        <SkillSlider
                            :id="`add_skill_${t.id}`"
                            :model-value="skillLevelFor(addForm.skills, t.id)"
                            @update:model-value="
                                (v) => {
                                    addForm.skills = setSkillLevel(addForm.skills, t.id, v);
                                }
                            "
                        />
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeAdd">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="addForm.processing">Save member</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Import members modal -->
        <Modal :show="showImport && canManage" max-width="lg" @close="closeImport">
            <form class="p-6" @submit.prevent="submitImport">
                <h2 class="text-lg font-semibold text-brand-navy">Import members</h2>
                <p class="mt-2 mb-4 text-sm text-brand-blue/70">
                    Upload a spreadsheet from Excel (<code class="rounded bg-brand-mist px-1">.csv</code> or
                    <code class="rounded bg-brand-mist px-1">.xlsx</code>). Use a header row with
                    <code class="rounded bg-brand-mist px-1">first_name</code> and/or
                    <code class="rounded bg-brand-mist px-1">last_name</code> (any column order; extra
                    columns are ignored). Optional:
                    <code class="rounded bg-brand-mist px-1">email</code>,
                    <code class="rounded bg-brand-mist px-1">phone</code>,
                    <code class="rounded bg-brand-mist px-1">company</code>,
                    <code class="rounded bg-brand-mist px-1">sector</code>,
                    <code class="rounded bg-brand-mist px-1">notes</code>.
                </p>
                <label class="block">
                    <span class="text-sm font-medium text-brand-navy">File</span>
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".csv,.txt,.xlsx,.xls"
                        class="mt-2 block w-full text-sm text-brand-navy file:mr-3 file:rounded-lg file:border-0 file:bg-brand-mist file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-brand-blue/10"
                        @change="pickFile"
                    />
                </label>
                <p v-if="importForm.errors.file" class="mt-2 text-sm text-red-600">
                    {{ importForm.errors.file }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeImport">Cancel</SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="importForm.processing || !importForm.file"
                    >
                        Import
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Edit member modal -->
        <Modal :show="showEdit && canManage" max-width="xl" @close="closeEdit">
            <form class="max-h-[80vh] overflow-y-auto p-6" @submit.prevent="saveEdit">
                <h3 class="text-lg font-semibold text-brand-navy">Edit member</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField label="First name" name="edit_fn" :error="editForm.errors.first_name" required>
                        <TextInput id="edit_fn" v-model="editForm.first_name" :error="!!editForm.errors.first_name" />
                    </FormField>
                    <FormField label="Last name" name="edit_ln" :error="editForm.errors.last_name">
                        <TextInput id="edit_ln" v-model="editForm.last_name" :error="!!editForm.errors.last_name" />
                    </FormField>
                </div>
                <FormField label="Gender" name="edit_gender" :error="editForm.errors.gender" class="mt-4">
                    <ListboxInput v-model="editForm.gender" :options="genderOptions" />
                </FormField>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField label="Email" name="edit_email" :error="editForm.errors.email">
                        <TextInput id="edit_email" v-model="editForm.email" :error="!!editForm.errors.email" />
                    </FormField>
                    <FormField label="Phone" name="edit_phone" :error="editForm.errors.phone">
                        <TextInput id="edit_phone" v-model="editForm.phone" :error="!!editForm.errors.phone" />
                    </FormField>
                </div>
                <FormField label="Organization / Company" name="edit_co" :error="editForm.errors.company" class="mt-4">
                    <TextInput id="edit_co" v-model="editForm.company" :error="!!editForm.errors.company" />
                </FormField>
                <div class="mt-4">
                    <span class="block text-sm font-medium text-brand-navy">Sector</span>
                    <ComboboxInput
                        v-model="editForm.sector_id"
                        :options="sectorOptions"
                        placeholder="Search sector…"
                        :error="!!editForm.errors.sector_id"
                    />
                </div>
                <FormField label="Notes" name="edit_notes" :error="editForm.errors.notes" class="mt-4">
                    <textarea
                        id="edit_notes"
                        v-model="editForm.notes"
                        rows="2"
                        class="ts-input w-full resize-y rounded-lg py-2 text-sm"
                        :class="editForm.errors.notes ? 'ts-input-error' : ''"
                    />
                </FormField>

                <div v-if="eventTypes?.length" class="mt-6 space-y-4 border-t border-brand-mist pt-4">
                    <p class="text-sm font-medium text-brand-navy">Skills</p>
                    <div
                        v-for="t in eventTypes"
                        :key="`e${t.id}`"
                        class="rounded-lg border border-brand-mist/80 bg-brand-cream/30 px-3 py-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-medium text-brand-navy">{{ t.name }}</span>
                            <span class="text-xs text-brand-blue/60">
                                {{ skillLevelFor(editForm.skills, t.id) }}
                            </span>
                        </div>
                        <SkillSlider
                            :id="`edit_skill_${t.id}`"
                            :model-value="skillLevelFor(editForm.skills, t.id)"
                            @update:model-value="
                                (v) => {
                                    editForm.skills = setSkillLevel(editForm.skills, t.id, v);
                                }
                            "
                        />
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeEdit">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="destroyId !== null"
            title="Remove member?"
            message="They can be restored later from this list."
            confirm-label="Remove"
            :processing="destroyProcessing"
            @close="closeRemove"
            @confirm="runRemove"
        />
    </OrganizationLayout>
</template>
