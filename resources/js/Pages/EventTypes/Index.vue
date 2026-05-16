<script setup>
import Alert from '@/Components/Alert.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import DangerButton from '@/Components/DangerButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import { parseOptionalSortOrder, requireTrimmedName } from '@/utils/formValidation';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    organization: Object,
    eventTypes: Array,
});

const canManage = computed(() =>
    ['admin', 'organizer'].includes(props.organization?.role),
);

const addForm = useForm({
    name: '',
    sort_order: '',
});

const validateAddForm = () => {
    addForm.clearErrors();
    let valid = true;

    const name = requireTrimmedName(addForm.name);
    if (!name.ok) {
        addForm.setError('name', name.message);
        valid = false;
    }

    const sort = parseOptionalSortOrder(addForm.sort_order);
    if (!sort.ok) {
        addForm.setError('sort_order', sort.message);
        valid = false;
    }

    return { valid, name, sort };
};

const focusField = async (id) => {
    await nextTick();
    document.getElementById(id)?.focus();
};

const submitAdd = () => {
    const { valid, name, sort } = validateAddForm();
    if (!valid) {
        focusField(addForm.errors.name ? 'new_name' : 'new_sort');

        return;
    }

    addForm.name = name.value;
    addForm
        .transform((data) => ({
            name: name.value,
            sort_order: sort.value,
        }))
        .post(route('organizations.event-types.store', props.organization.slug), {
            preserveScroll: true,
            onSuccess: () => addForm.reset('name', 'sort_order'),
            onError: () => focusField(addForm.errors.name ? 'new_name' : 'new_sort'),
        });
};

const editingId = ref(null);
const editForm = useForm({
    name: '',
    sort_order: '',
});

const validateEditForm = () => {
    editForm.clearErrors();
    let valid = true;

    const name = requireTrimmedName(editForm.name);
    if (!name.ok) {
        editForm.setError('name', name.message);
        valid = false;
    }

    const sort = parseOptionalSortOrder(editForm.sort_order);
    if (!sort.ok) {
        editForm.setError('sort_order', sort.message);
        valid = false;
    }

    return { valid, name, sort };
};

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

    const { valid, name, sort } = validateEditForm();
    if (!valid) {
        focusField(
            editForm.errors.name ? `edit_name_${editingId.value}` : `edit_sort_${editingId.value}`,
        );

        return;
    }

    editForm.name = name.value;
    editForm
        .transform(() => ({
            name: name.value,
            sort_order: sort.value,
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
                onError: () =>
                    focusField(
                        editForm.errors.name
                            ? `edit_name_${editingId.value}`
                            : `edit_sort_${editingId.value}`,
                    ),
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
        route('organizations.event-types.destroy', [
            props.organization.slug,
            destroyId.value,
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                destroyProcessing.value = false;
                closeDestroy();
            },
        },
    );
};

const addFormHasErrors = computed(
    () => Object.keys(addForm.errors).length > 0 && !addForm.processing,
);
</script>

<template>
    <Head title="Event types" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="ts-heading-page">Event types</h1>
                    <p class="mt-1 text-sm text-brand-blue/70">
                        Categories for events and member skill ratings (for example Golf, Dinner).
                    </p>
                </div>
                <Link
                    :href="route('organizations.events.index', organization.slug)"
                    class="text-sm font-medium text-brand-blue hover:text-brand-navy"
                >
                    ← Back to events
                </Link>
            </div>
        </template>

        <div
            v-if="canManage"
            class="ts-card-padded mb-8"
        >
            <h2 class="ts-heading-section mb-4">Add type</h2>
            <Alert
                v-if="addFormHasErrors"
                variant="error"
                class="mb-4"
                role="alert"
            >
                Please fix the errors below before adding the event type.
            </Alert>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitAdd">
                <FormField
                    label="Name"
                    name="new_name"
                    :error="addForm.errors.name"
                    hint="Required. Shown when creating events and on member skill sliders."
                    required
                >
                    <TextInput
                        id="new_name"
                        v-model="addForm.name"
                        :error="!!addForm.errors.name"
                        autocomplete="off"
                        required
                    />
                </FormField>
                <FormField
                    label="Sort order"
                    name="new_sort"
                    :error="addForm.errors.sort_order"
                    hint="Optional. Lower numbers appear first. Leave blank to append at the end."
                >
                    <TextInput
                        id="new_sort"
                        v-model="addForm.sort_order"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        :error="!!addForm.errors.sort_order"
                    />
                </FormField>
                <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                    <PrimaryButton :disabled="addForm.processing">
                        {{ addForm.processing ? 'Adding…' : 'Add event type' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <EmptyState
            v-if="!eventTypes?.length"
            title="No event types"
            description="Create at least one event type before scheduling events."
        />

        <ul
            v-else
            class="divide-y divide-brand-mist overflow-hidden rounded-xl border border-brand-mist bg-white shadow-sm"
        >
            <li
                v-for="t in eventTypes"
                :key="t.id"
                class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <template v-if="editingId === t.id">
                    <form class="flex w-full flex-1 flex-col gap-4" @submit.prevent="saveEdit">
                        <Alert
                            v-if="Object.keys(editForm.errors).length"
                            variant="error"
                            role="alert"
                        >
                            Please fix the errors below before saving.
                        </Alert>
                        <div class="flex flex-1 flex-wrap items-end gap-4">
                            <FormField
                                label="Name"
                                :name="`edit_name_${t.id}`"
                                :error="editForm.errors.name"
                                required
                            >
                                <TextInput
                                    :id="`edit_name_${t.id}`"
                                    v-model="editForm.name"
                                    class="min-w-[12rem]"
                                    :error="!!editForm.errors.name"
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Sort"
                                :name="`edit_sort_${t.id}`"
                                :error="editForm.errors.sort_order"
                                hint="Leave blank to keep the current order."
                            >
                                <TextInput
                                    :id="`edit_sort_${t.id}`"
                                    v-model="editForm.sort_order"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    class="w-24"
                                    :error="!!editForm.errors.sort_order"
                                />
                            </FormField>
                            <div class="flex gap-2">
                                <SecondaryButton type="button" @click="cancelEdit">
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton type="submit" :disabled="editForm.processing">
                                    {{ editForm.processing ? 'Saving…' : 'Save' }}
                                </PrimaryButton>
                            </div>
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
                        <DangerButton type="button" @click="confirmDestroy(t.id)">
                            Delete
                        </DangerButton>
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
            message="Events referencing this type must be changed first. This cannot be undone."
            confirm-label="Delete"
            :processing="destroyProcessing"
            @close="closeDestroy"
            @confirm="runDestroy"
        />
    </OrganizationLayout>
</template>
