<script setup>
import ComboboxInput from '@/Components/ComboboxInput.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SkillSlider from '@/Components/SkillSlider.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
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

const quickSectorForm = useForm({
    name: '',
});

const submitQuickSector = () => {
    quickSectorForm.post(route('organizations.sectors.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => quickSectorForm.reset('name'),
    });
};

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
        },
    });
};

const defaultSkillsPayload = () =>
    (props.eventTypes || []).map((t) => ({
        event_type_id: t.id,
        skill_level: 50,
    }));

const addForm = useForm({
    first_name: '',
    last_name: '',
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
        },
    });
};

const editingId = ref(null);
const showEdit = ref(false);

const editForm = useForm({
    first_name: '',
    last_name: '',
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
</script>

<template>
    <Head title="Members" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h1 class="ts-heading-page">Members</h1>
                <div class="flex flex-wrap gap-3 text-sm">
                    <Link
                        :href="route('organizations.sectors.index', organization.slug)"
                        class="font-medium text-brand-blue hover:text-brand-navy"
                    >
                        Manage sectors
                    </Link>
                </div>
            </div>
        </template>

        <div v-if="canManage" class="mb-10 grid gap-8 lg:grid-cols-2">
            <form class="ts-card-padded space-y-4" @submit.prevent="submitAdd">
                <h2 class="ts-heading-section">Add member</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="First name" name="add_fn" :error="addForm.errors.first_name" required>
                        <TextInput id="add_fn" v-model="addForm.first_name" :error="!!addForm.errors.first_name" />
                    </FormField>
                    <FormField label="Last name" name="add_ln" :error="addForm.errors.last_name">
                        <TextInput id="add_ln" v-model="addForm.last_name" :error="!!addForm.errors.last_name" />
                    </FormField>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Email" name="add_email" :error="addForm.errors.email">
                        <TextInput id="add_email" v-model="addForm.email" :error="!!addForm.errors.email" />
                    </FormField>
                    <FormField label="Phone" name="add_phone" :error="addForm.errors.phone">
                        <TextInput id="add_phone" v-model="addForm.phone" :error="!!addForm.errors.phone" />
                    </FormField>
                </div>
                <FormField label="Organization / Company" name="add_co" :error="addForm.errors.company">
                    <TextInput id="add_co" v-model="addForm.company" :error="!!addForm.errors.company" />
                </FormField>
                <div>
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

                <div class="rounded-lg border border-brand-mist bg-brand-cream/40 p-4">
                    <p class="text-sm font-medium text-brand-navy">Quick add sector</p>
                    <p class="mb-3 text-xs text-brand-blue/65">
                        Name must be unique for this org (or add via the sectors page).
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <TextInput
                            v-model="quickSectorForm.name"
                            class="min-w-[12rem] flex-1"
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

                <FormField label="Notes" name="add_notes" :error="addForm.errors.notes">
                    <textarea
                        id="add_notes"
                        v-model="addForm.notes"
                        rows="2"
                        class="ts-input w-full resize-y rounded-lg py-2 text-sm"
                        :class="addForm.errors.notes ? 'ts-input-error' : ''"
                    />
                </FormField>

                <div v-if="eventTypes?.length" class="space-y-4 border-t border-brand-mist pt-4">
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

                <PrimaryButton :disabled="addForm.processing">Save member</PrimaryButton>
            </form>

            <form class="ts-card-padded" @submit.prevent="submitImport">
                <h2 class="ts-heading-section mb-2">Import members</h2>
                <p class="mb-4 text-sm text-brand-blue/70">
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
                <div class="mt-4">
                    <PrimaryButton
                        type="submit"
                        :disabled="importForm.processing || !importForm.file"
                    >
                        Import
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <EmptyState
            v-if="!members?.length"
            title="No members"
            description="Add people to your organization or import a CSV."
        />

        <div v-else class="overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-mist">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            Name
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            Contact
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70">
                            Org / Company
                        </th>
                        <th class="hidden px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-brand-blue/70 md:table-cell">
                            Sector
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
                        v-for="m in members"
                        :key="m.id"
                        :class="m.deleted_at ? 'bg-brand-cream/80 opacity-80' : ''"
                    >
                        <td class="px-4 py-3 text-sm text-brand-navy">
                            {{ m.first_name }}
                            <span v-if="m.last_name">{{ ' ' + m.last_name }}</span>
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
