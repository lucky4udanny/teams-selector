<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const props = defineProps({
    organization: Object,
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    pendingInvitations: { type: Array, default: () => [] },
});

const usersList = computed(() => props.users ?? []);
const rolesList = computed(() => props.roles ?? []);

const addForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'organizer',
});

const submitAdd = () => {
    addForm.post(route('organizations.users.store', props.organization.slug), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
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
        route('organizations.users.destroy', [props.organization.slug, userId]),
    );
};

const resetTarget = ref(null);
const showResetModal = ref(false);

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const openResetPassword = (user) => {
    resetTarget.value = user;
    passwordForm.clearErrors();
    passwordForm.reset();
    showResetModal.value = true;
};

const closeResetModal = () => {
    showResetModal.value = false;
    resetTarget.value = null;
    passwordForm.reset();
};

const submitResetPassword = () => {
    if (!resetTarget.value) return;

    passwordForm.patch(
        route('organizations.users.password.update', [
            props.organization.slug,
            resetTarget.value.id,
        ]),
        {
            preserveScroll: true,
            onSuccess: () => closeResetModal(),
        },
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
            <h2 class="mb-1 text-sm font-semibold text-brand-navy">Add user</h2>
            <p class="mb-4 text-xs text-brand-blue/70">
                Create an account and add them to this organization. Share the login email and password with them securely.
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        v-model="addForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autocomplete="name"
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
                        required
                        autocomplete="username"
                    />
                    <InputError :message="addForm.errors.email" />
                </div>
                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        v-model="addForm.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="addForm.errors.password" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Confirm password" />
                    <TextInput
                        id="password_confirmation"
                        v-model="addForm.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                    />
                </div>
                <div>
                    <InputLabel for="role" value="Role" />
                    <select
                        id="role"
                        v-model="addForm.role"
                        class="mt-1 block w-full rounded-md border-brand-mist shadow-sm"
                    >
                        <option v-for="r in rolesList" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                    <InputError :message="addForm.errors.role" />
                </div>
            </div>
            <div class="mt-4">
                <PrimaryButton :disabled="addForm.processing">Add user</PrimaryButton>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-brand-mist bg-white shadow-sm">
            <div class="border-b border-brand-mist px-4 py-3">
                <h2 class="text-sm font-semibold text-brand-navy">Active users</h2>
            </div>
            <table class="min-w-full divide-y divide-brand-mist text-sm">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Role</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-brand-blue/70">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr v-for="u in usersList" :key="u.id">
                        <td class="px-4 py-3">{{ u.name }}</td>
                        <td class="px-4 py-3">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <select
                                :value="u.role"
                                class="rounded border-brand-mist text-sm"
                                @change="updateRole(u.id, $event.target.value)"
                            >
                                <option v-for="r in rolesList" :key="r" :value="r">
                                    {{ r }}
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <SecondaryButton
                                    v-if="u.id !== currentUserId"
                                    type="button"
                                    @click="openResetPassword(u)"
                                >
                                    Reset password
                                </SecondaryButton>
                                <DangerButton
                                    v-if="u.id !== currentUserId"
                                    type="button"
                                    @click="remove(u.id)"
                                >
                                    Remove
                                </DangerButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showResetModal" max-width="md" @close="closeResetModal">
            <form class="p-6" @submit.prevent="submitResetPassword">
                <h2 class="text-lg font-semibold text-brand-navy">Reset password</h2>
                <p v-if="resetTarget" class="mt-1 text-sm text-brand-blue/70">
                    Set a new password for {{ resetTarget.name }} ({{ resetTarget.email }}).
                </p>
                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="reset_password" value="New password" />
                        <TextInput
                            id="reset_password"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="reset_password_confirmation" value="Confirm password" />
                        <TextInput
                            id="reset_password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            autocomplete="new-password"
                        />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeResetModal">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="passwordForm.processing">Update password</PrimaryButton>
                </div>
            </form>
        </Modal>
    </OrganizationLayout>
</template>
