<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    organization: Object,
    users: Array,
    roles: Array,
});

const addForm = useForm({
    email: '',
    role: 'organizer',
});

const submitAdd = () => {
    addForm.post(
        route('organizations.users.store', props.organization.slug),
        { preserveScroll: true, onSuccess: () => addForm.reset() },
    );
};

const updateRole = (userId, role) => {
    router.patch(
        route('organizations.users.update', [props.organization.slug, userId]),
        { role },
        { preserveScroll: true },
    );
};

const remove = (userId) => {
    if (!confirm('Remove user from organization?')) return;
    router.delete(
        route('organizations.users.destroy', [
            props.organization.slug,
            userId,
        ]),
    );
};
</script>

<template>
    <Head title="Users" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-brand-navy">Organization users</h1>
        </template>

        <form
            class="mb-8 rounded-2xl border border-brand-mist bg-white p-6 shadow-sm"
            @submit.prevent="submitAdd"
        >
            <h2 class="mb-4 text-sm font-semibold text-brand-navy">Add user</h2>
            <p class="mb-4 text-xs text-brand-blue/70">
                User must already have an account (registered email).
            </p>
            <div class="flex flex-wrap items-end gap-4">
                <div class="min-w-[200px] flex-1">
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="addForm.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="addForm.errors.email" />
                </div>
                <div>
                    <InputLabel for="role" value="Role" />
                    <select
                        id="role"
                        v-model="addForm.role"
                        class="mt-1 block rounded-md border-brand-mist shadow-sm"
                    >
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                </div>
                <PrimaryButton :disabled="addForm.processing">Add</PrimaryButton>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-brand-mist bg-white shadow-sm">
            <table class="min-w-full divide-y divide-brand-mist text-sm">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Name
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Email
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">
                            Role
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-brand-blue/70">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr v-for="u in users" :key="u.id">
                        <td class="px-4 py-3">{{ u.name }}</td>
                        <td class="px-4 py-3">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <select
                                :value="u.role"
                                class="rounded border-brand-mist text-sm"
                                @change="
                                    updateRole(u.id, $event.target.value)
                                "
                            >
                                <option v-for="r in roles" :key="r" :value="r">
                                    {{ r }}
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <DangerButton type="button" @click="remove(u.id)">
                                Remove
                            </DangerButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </OrganizationLayout>
</template>
