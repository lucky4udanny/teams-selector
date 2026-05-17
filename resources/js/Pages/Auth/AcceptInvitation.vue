<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    invitation: Object,
    userExists: Boolean,
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('invitations.accept', props.invitation.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Accept Invitation" />

        <!-- Invitation context -->
        <div class="mb-6 rounded-xl bg-brand-navy/5 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-blue/60">
                Invitation from {{ invitation.invited_by_name }}
            </p>
            <p class="mt-1 text-base font-semibold text-brand-navy">
                Join <span class="text-brand-blue">{{ invitation.organization_name }}</span>
                as <span class="capitalize">{{ invitation.role }}</span>
            </p>
            <p class="mt-1 text-sm text-brand-blue/70">{{ invitation.email }}</p>
        </div>

        <!-- Existing account: prompt to log in -->
        <div v-if="userExists" class="space-y-4">
            <p class="text-sm text-brand-navy">
                An account with this email already exists. Log in to accept this invitation.
            </p>
            <Link
                :href="route('login')"
                class="block w-full rounded-lg bg-brand-navy px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-blue"
            >
                Log in to accept
            </Link>
        </div>

        <!-- New account: register form -->
        <form v-else @submit.prevent="submit">
            <p class="mb-4 text-sm text-brand-blue/70">
                Create your account to join the organization.
            </p>

            <div>
                <InputLabel for="name" value="Your name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full cursor-not-allowed bg-brand-cream/60"
                    :value="invitation.email"
                    disabled
                />
                <p class="mt-1 text-xs text-brand-blue/50">
                    This cannot be changed — the invitation is tied to this email.
                </p>
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-6">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Create account &amp; join {{ invitation.organization_name }}
                </PrimaryButton>
            </div>

            <p class="mt-4 text-center text-xs text-brand-blue/50">
                Already have an account?
                <Link :href="route('login')" class="underline hover:text-brand-navy">Log in</Link>
            </p>
        </form>
    </GuestLayout>
</template>
