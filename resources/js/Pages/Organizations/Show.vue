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
    brand_primary: props.organization.brand_primary || '#1a6893',
    brand_accent: props.organization.brand_accent || '#f7941c',
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
            <h1 class="ts-heading-page">
                {{ organization.name }}
            </h1>
        </template>

        <div class="grid gap-6 md:grid-cols-2">
            <Link
                :href="route('organizations.members.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-blue">
                    <UsersIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Members</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ organization.counts.members }} people
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.rules.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-orange">
                    <WrenchScrewdriverIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Rules</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ organization.counts.rules }} defined
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.drafts.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-sky">
                    <ClipboardDocumentListIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Drafts</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ organization.counts.drafts }} open
                    </p>
                </div>
            </Link>
            <Link
                :href="route('organizations.selections.index', organization.slug)"
                class="ts-card-interactive"
            >
                <div class="ts-icon-tile-navy">
                    <UserGroupIcon class="h-7 w-7" />
                </div>
                <div>
                    <p class="font-semibold text-brand-navy">Selections</p>
                    <p class="text-sm text-brand-blue/70">
                        {{ organization.counts.approved }} approved
                    </p>
                </div>
            </Link>
        </div>

        <section
            v-if="isAdmin"
            class="ts-card-padded mt-10"
        >
            <h2 class="ts-heading-section mb-4">Branding</h2>
            <form class="space-y-4" @submit.prevent="saveSettings">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="brand_primary" value="Primary" />
                        <TextInput
                            id="brand_primary"
                            v-model="settingsForm.brand_primary"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="#1a6893"
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
                            placeholder="#f7941c"
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
                        class="mt-1 block w-full text-sm text-brand-blue/80"
                        @change="pickLogo"
                    />
                    <InputError
                        class="mt-2"
                        :message="settingsForm.errors.logo"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-brand-navy/90">
                    <input
                        v-model="settingsForm.remove_logo"
                        type="checkbox"
                        class="rounded border-brand-mist"
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
