<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    organization: Object,
    members: Array,
    canManage: Boolean,
});

const addForm = useForm({
    name: '',
    email: '',
    notes: '',
});

const submitAdd = () => {
    addForm.post(
        route('organizations.members.store', props.organization.slug),
        { preserveScroll: true, onSuccess: () => addForm.reset() },
    );
};

const importForm = useForm({
    file: null,
});

const pickFile = (e) => {
    importForm.file = e.target.files?.[0] ?? null;
};

const submitImport = () => {
    importForm.post(
        route('organizations.members.import', props.organization.slug),
        { forceFormData: true, preserveScroll: true, onSuccess: () => importForm.reset('file') },
    );
};

const editForm = useForm({
    name: '',
    email: '',
    notes: '',
});

const editingId = ref(null);

const startEdit = (m) => {
    editingId.value = m.id;
    editForm.name = m.name;
    editForm.email = m.email || '';
    editForm.notes = m.notes || '';
};

const saveEdit = () => {
    if (!editingId.value) return;
    editForm.patch(
        route('organizations.members.update', [
            props.organization.slug,
            editingId.value,
        ]),
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
};

const remove = (id) => {
    if (!confirm('Remove this member?')) return;
    router.delete(
        route('organizations.members.destroy', [
            props.organization.slug,
            id,
        ]),
    );
};

const restore = (id) => {
    router.post(
        route('organizations.members.restore', [props.organization.slug, id]),
    );
};

const cancelEdit = () => {
    editingId.value = null;
};
</script>

<template>
    <Head title="Members" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-brand-navy">Members</h1>
        </template>

        <div v-if="canManage" class="mb-8 grid gap-6 lg:grid-cols-2">
            <form
                class="rounded-2xl border border-brand-mist bg-white p-6 shadow-sm"
                @submit.prevent="submitAdd"
            >
                <h2 class="mb-4 text-sm font-semibold text-brand-navy">Add</h2>
                <div class="space-y-3">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="addForm.name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="addForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            v-model="addForm.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <TextInput
                            id="notes"
                            v-model="addForm.notes"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="addForm.errors.notes" />
                    </div>
                </div>
                <div class="mt-4">
                    <PrimaryButton :disabled="addForm.processing">Add</PrimaryButton>
                </div>
            </form>

            <form
                class="rounded-2xl border border-brand-mist bg-white p-6 shadow-sm"
                @submit.prevent="submitImport"
            >
                <h2 class="mb-2 text-sm font-semibold text-brand-navy">CSV import</h2>
                <p class="mb-4 text-xs text-brand-blue/70">
                    Header: <code>name</code>, optional <code>email</code>,
                    <code>notes</code>
                </p>
                <input
                    type="file"
                    accept=".csv,.txt"
                    class="block w-full text-sm"
                    @change="pickFile"
                />
                <InputError class="mt-2" :message="importForm.errors.file" />
                <div class="mt-4">
                    <SecondaryButton :disabled="importForm.processing || !importForm.file"
                        >Import</SecondaryButton
                    >
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-brand-mist bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-mist">
                <thead class="bg-brand-cream">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70"
                        >
                            Name
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70"
                        >
                            Email
                        </th>
                        <th
                            v-if="canManage"
                            class="px-4 py-3 text-right text-xs font-medium uppercase text-brand-blue/70"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr
                        v-for="m in members"
                        :key="m.id"
                        :class="m.deleted_at ? 'bg-brand-cream opacity-70' : ''"
                    >
                        <td class="px-4 py-3 text-sm text-brand-navy">
                            {{ m.name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-brand-blue/80">
                            {{ m.email || '—' }}
                        </td>
                        <td v-if="canManage" class="px-4 py-3 text-right text-sm">
                            <template v-if="!m.deleted_at">
                                <button
                                    type="button"
                                    class="text-brand-blue hover:underline"
                                    @click="startEdit(m)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="ms-3 text-red-600 hover:underline"
                                    @click="remove(m.id)"
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

        <div
            v-if="editingId && canManage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="cancelEdit"
        >
            <form
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
                @submit.prevent="saveEdit"
            >
                <h3 class="mb-4 font-semibold text-brand-navy">Edit member</h3>
                <div class="space-y-3">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="editForm.name" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <TextInput
                            v-model="editForm.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="Notes" />
                        <TextInput v-model="editForm.notes" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.notes" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="cancelEdit"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </div>
    </OrganizationLayout>
</template>
