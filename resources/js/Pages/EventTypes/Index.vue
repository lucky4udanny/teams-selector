<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    organization: Object,
    eventTypes: Array,
});

const canManage = computed(() =>
    ['admin', 'organizer'].includes(props.organization?.role),
);

const normalizeSortOrder = (value) =>
    value === '' || value == null ? null : Number(value);

const addForm = useForm({
    name: '',
    sort_order: '',
});

const submitAdd = () => {
    addForm
        .transform((data) => ({
            ...data,
            sort_order: normalizeSortOrder(data.sort_order),
        }))
        .post(route('organizations.event-types.store', props.organization.slug), {
            preserveScroll: true,
            onSuccess: () => addForm.reset('name', 'sort_order'),
        });
};

const editingId = ref(null);
const editForm = useForm({
    name: '',
    sort_order: '',
});

const startEdit = (t) => {
    editingId.value = t.id;
    editForm.name = t.name;
    editForm.sort_order =
        t.sort_order != null && t.sort_order !== '' ? String(t.sort_order) : '';
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.clearErrors();
};

const saveEdit = () => {
    if (!editingId.value) {
        return;
    }
    editForm
        .transform((data) => ({
            ...data,
            sort_order: normalizeSortOrder(data.sort_order),
        }))
        .patch(
            route('organizations.event-types.update', [
                props.organization.slug,
                editingId.value,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    editingId.value = null;
                    editForm.clearErrors();
                },
            },
        );
};

const destroyId = ref(null);
const destroyProcessing = ref(false);

const confirmDestroy = (id) => {
    destroyId.value = id;
};

const closeDestroy = () => {
    destroyId.value = null;
};

const runDestroy = () => {
    if (!destroyId.value) {
        return;
    }
    destroyProcessing.value = true;
    router.delete(
        route('organizations.event-types.destroy', [props.organization.slug, destroyId.value]),
        {
            preserveScroll: true,
            onFinish: () => {
                destroyProcessing.value = false;
                closeDestroy();
            },
        },
    );
};
</script>

<template>
    <Head title="Event types" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div>
                <h1 class="ts-heading-page">Event types</h1>
            </div>
        </template>

        <div
            v-if="canManage"
            class="ts-card-padded mb-8"
        >
            <h2 class="ts-heading-section mb-4">Add type</h2>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitAdd">
                <FormField label="Name" name="new_name" :error="addForm.errors.name" required>
                    <TextInput
                        id="new_name"
                        v-model="addForm.name"
                        :error="!!addForm.errors.name"
                    />
                </FormField>
                <FormField
                    label="Sort order"
                    name="new_sort"
                    :error="addForm.errors.sort_order"
                >
                    <TextInput
                        id="new_sort"
                        v-model="addForm.sort_order"
                        inputmode="numeric"
                        :error="!!addForm.errors.sort_order"
                    />
                </FormField>
                <div class="md:col-span-2">
                    <PrimaryButton :disabled="addForm.processing">Add</PrimaryButton>
                </div>
            </form>
        </div>

        <EmptyState
            v-if="!eventTypes?.length"
            title="No event types"
            description="Create at least one event type before scheduling events."
        />

        <ul v-else class="divide-y divide-brand-mist overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm">
            <li
                v-for="t in eventTypes"
                :key="t.id"
                class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <template v-if="editingId === t.id">
                    <form class="flex flex-1 flex-wrap items-end gap-4" @submit.prevent="saveEdit">
                        <FormField label="Name" :name="`edit_name_${t.id}`" :error="editForm.errors.name" required>
                            <TextInput
                                :id="`edit_name_${t.id}`"
                                v-model="editForm.name"
                                class="min-w-[12rem]"
                                :error="!!editForm.errors.name"
                            />
                        </FormField>
                        <FormField
                            label="Sort"
                            :name="`edit_sort_${t.id}`"
                            :error="editForm.errors.sort_order"
                        >
                            <TextInput
                                :id="`edit_sort_${t.id}`"
                                v-model="editForm.sort_order"
                                inputmode="numeric"
                                class="w-24"
                                :error="!!editForm.errors.sort_order"
                            />
                        </FormField>
                        <div class="flex gap-2">
                            <SecondaryButton type="button" @click="cancelEdit">Cancel</SecondaryButton>
                            <PrimaryButton type="submit" :disabled="editForm.processing">Save</PrimaryButton>
                        </div>
                    </form>
                </template>
                <template v-else>
                    <div>
                        <p class="font-medium text-brand-navy">{{ t.name }}</p>
                        <p class="text-sm text-brand-blue/60">Sort order: {{ t.sort_order }}</p>
                    </div>
                    <div v-if="canManage" class="flex flex-wrap gap-2">
                        <SecondaryButton type="button" @click="startEdit(t)">Edit</SecondaryButton>
                        <DangerButton type="button" @click="confirmDestroy(t.id)">Delete</DangerButton>
                    </div>
                </template>
            </li>
        </ul>

        <p v-if="!canManage" class="mt-6 text-sm text-brand-blue/70">
            Ask an organizer or admin to manage event types.
        </p>

        <ConfirmDialog
            :show="destroyId !== null"
            title="Delete this event type?"
            message="Events referencing this type may be affected. This cannot be undone."
            confirm-label="Delete"
            :processing="destroyProcessing"
            @close="closeDestroy"
            @confirm="runDestroy"
        />
    </OrganizationLayout>
</template>
