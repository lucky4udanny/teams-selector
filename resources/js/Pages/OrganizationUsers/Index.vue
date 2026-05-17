<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    organization: Object,
    users: Array,
    roles: Array,
    pendingInvitations: Array,
});

const inviteForm = useForm({
    email: '',
    role: 'organizer',
});

const submitInvite = () => {
    inviteForm.post(
        route('organizations.invitations.store', props.organization.slug),
        { preserveScroll: true, onSuccess: () => inviteForm.reset() },
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
        route('organizations.users.destroy', [props.organization.slug, userId]),
    );
};

const revokeInvitation = (invitationId) => {
    if (!confirm('Revoke this invitation?')) return;
    router.delete(
        route('organizations.invitations.destroy', [props.organization.slug, invitationId]),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Users" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-brand-navy">Organization users</h1>
        </template>

        <!-- Invite user form -->
        <form
            class="mb-8 rounded-2xl border border-brand-mist bg-white p-6 shadow-sm"
            @submit.prevent="submitInvite"
        >
            <h2 class="mb-1 text-sm font-semibold text-brand-navy">Invite user</h2>
            <p class="mb-4 text-xs text-brand-blue/70">
                An email invitation will be sent. The link expires in 7 days.
            </p>
            <div class="flex flex-wrap items-end gap-4">
                <div class="min-w-[200px] flex-1">
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="inviteForm.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="inviteForm.errors.email" />
                </div>
                <div>
                    <InputLabel for="role" value="Role" />
                    <select
                        id="role"
                        v-model="inviteForm.role"
                        class="mt-1 block rounded-md border-brand-mist shadow-sm"
                    >
                        <option v-for="r in roles" :key="r" :value="r">
                            {{ r }}
                        </option>
                    </select>
                </div>
                <PrimaryButton :disabled="inviteForm.processing">Send invitation</PrimaryButton>
            </div>
        </form>

        <!-- Pending invitations -->
        <div
            v-if="pendingInvitations.length"
            class="mb-8 overflow-hidden rounded-2xl border border-brand-mist bg-white shadow-sm"
        >
            <div class="border-b border-brand-mist px-4 py-3">
                <h2 class="text-sm font-semibold text-brand-navy">Pending invitations</h2>
            </div>
            <table class="min-w-full divide-y divide-brand-mist text-sm">
                <thead class="bg-brand-cream">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Invited by</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-brand-blue/70">Expires</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-brand-blue/70">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-mist">
                    <tr v-for="inv in pendingInvitations" :key="inv.id">
                        <td class="px-4 py-3 text-brand-navy">{{ inv.email }}</td>
                        <td class="px-4 py-3 capitalize text-brand-blue/80">{{ inv.role }}</td>
                        <td class="px-4 py-3 text-brand-blue/80">{{ inv.invited_by_name }}</td>
                        <td class="px-4 py-3 text-brand-blue/60">{{ inv.expires_at }}</td>
                        <td class="px-4 py-3 text-right">
                            <DangerButton type="button" @click="revokeInvitation(inv.id)">
                                Revoke
                            </DangerButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Active users -->
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
                    <tr v-for="u in users" :key="u.id">
                        <td class="px-4 py-3">{{ u.name }}</td>
                        <td class="px-4 py-3">{{ u.email }}</td>
                        <td class="px-4 py-3">
                            <select
                                :value="u.role"
                                class="rounded border-brand-mist text-sm"
                                @change="updateRole(u.id, $event.target.value)"
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
