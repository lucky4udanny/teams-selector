<script setup>
import OrganizationLayout from '@/Layouts/OrganizationLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ClipboardDocumentListIcon,
    UserGroupIcon,
    UsersIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    organization: Object,
});

const isAdmin = computed(() => props.organization.role === 'admin');

const settingsForm = useForm({
    brand_primary: props.organization.brand_primary || '#4f46e5',
    brand_accent: props.organization.brand_accent || '#6366f1',
    logo: null,
    remove_logo: false,
});

const pickLogo = (e) => {
    const f = e.target.files?.[0];
    settingsForm.logo = f || null;
};

const saveSettings = () => {
    settingsForm.post(
        route('organizations.settings.update', props.organization.slug),
        { forceFormData: true, preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="organization.name" />

    <OrganizationLayout :organization="organization">
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ organization.name }}
            </h1>
        </template>

        <div class="grid gap-6 md:grid-cols-2">
            <Link
                :href="route('organizations.members.index', organization.slug)"
                class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-200"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                >
                    <UsersIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-slate-900">Members</p>
                    <p class="text-sm text-slate-500">
                        {{ organization.counts.members }} people
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.rules.index', organization.slug)"
                class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-200"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-700"
                >
                    <WrenchScrewdriverIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-slate-900">Rules</p>
                    <p class="text-sm text-slate-500">
                        {{ organization.counts.rules }} defined
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.drafts.index', organization.slug)"
                class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-200"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-700"
                >
                    <ClipboardDocumentListIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-slate-900">Drafts</p>
                    <p class="text-sm text-slate-500">
                        {{ organization.counts.drafts }} open
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.selections.index', organization.slug)"
                class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-200"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                >
                    <UserGroupIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-slate-900">Selections</p>
                    <p class="text-sm text-slate-500">
                        {{ organization.counts.approved }} approved
                    </p>
                </div>
            </Link>
        </div>

        <section
            v-if="isAdmin"
            class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-sm font-semibold text-slate-800">Branding</h2>
            <form class="space-y-4" @submit.prevent="saveSettings">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="brand_primary" value="Primary" />
                        <TextInput
                            id="brand_primary"
                            v-model="settingsForm.brand_primary"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="#4f46e5"
                        />
                        <InputError
                            class="mt-2"
                            :message="settingsForm.errors.brand_primary"
                        />
                    </div>
                    <div>
                        <InputLabel for="brand_accent" value="Accent" />
                        <TextInput
                            id="brand_accent"
                            v-model="settingsForm.brand_accent"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="#6366f1"
                        />
                        <InputError
                            class="mt-2"
                            :message="settingsForm.errors.brand_accent"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel for="logo" value="Logo" />
                    <input
                        id="logo"
                        type="file"
                        accept="image/png,image/jpeg,image/gif,image/webp"
                        class="mt-1 block w-full text-sm text-slate-600"
                        @change="pickLogo"
                    />
                    <InputError
                        class="mt-2"
                        :message="settingsForm.errors.logo"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="settingsForm.remove_logo"
                        type="checkbox"
                        class="rounded border-slate-300"
                    />
                    Remove current logo
                </label>
                <div class="flex gap-2">
                    <PrimaryButton :disabled="settingsForm.processing"
                        >Save</PrimaryButton
                    >
                    <SecondaryButton
                        type="button"
                        @click="settingsForm.reset('logo')"
                        >Clear file</SecondaryButton
                    >
                </div>
            </form>
        </section>
    </OrganizationLayout>
</template>
